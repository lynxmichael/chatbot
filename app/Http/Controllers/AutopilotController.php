<?php

namespace App\Http\Controllers;

use App\Models\AiAction;
use App\Models\AiInsight;
use App\Models\Organization;
use App\Services\AI\Autopilot\ActionExecutor;
use App\Services\AI\Autopilot\AutopilotPolicy;
use App\Services\AI\Tools\ToolRegistry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AutopilotController extends Controller
{
    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly ActionExecutor $executor,
    ) {
    }

    /**
     * Réglages de l'Autopilot.
     */
    public function index(Request $request)
    {
        $organization = $this->organization($request);

        $settings = $organization->aiSettings();

        $policy = AutopilotPolicy::forOrganization($organization);

        $tools = collect($this->registry->all())
            ->map(fn ($tool) => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'is_write' => $tool->isWrite(),
                'enabled' => in_array($tool->name(), $policy->allowedActions(), true),
                'decision' => $policy->decide($tool),
            ])
            ->values();

        return Inertia::render('Autopilot/Index', [
            'settings' => [
                'level' => $policy->level(),
                'persona' => $settings['persona'] ?? null,
                'business_name' => $settings['business_name'] ?? null,
                'tone' => $policy->tone(),
                'language' => $policy->language(),
                'confidence_threshold' => $policy->confidenceThreshold(),
                'auto_close_after_hours' => $policy->autoCloseAfterHours(),
                'escalate_on_negative_sentiment' => $policy->escalatesOnNegativeSentiment(),
                'allowed_actions' => $policy->allowedActions(),
            ],

            'tools' => $tools,

            'statistics' => [
                'pending_actions' => AiAction::query()
                    ->where('organization_id', $organization->id)
                    ->pending()
                    ->count(),

                'actions_today' => AiAction::query()
                    ->where('organization_id', $organization->id)
                    ->whereDate('created_at', today())
                    ->count(),

                'open_insights' => AiInsight::query()
                    ->where('organization_id', $organization->id)
                    ->open()
                    ->count(),

                'critical_insights' => AiInsight::query()
                    ->where('organization_id', $organization->id)
                    ->open()
                    ->critical()
                    ->count(),
            ],
        ]);
    }

    /**
     * Enregistrement des réglages.
     */
    public function update(Request $request)
    {
        $organization = $this->organization($request);

        $validated = $request->validate([
            'level' => ['required', 'in:off,suggest,assist,auto'],
            'persona' => ['nullable', 'string', 'max:120'],
            'business_name' => ['nullable', 'string', 'max:120'],
            'tone' => ['nullable', 'string', 'max:200'],
            'language' => ['nullable', 'string', 'max:10'],
            'confidence_threshold' => ['required', 'numeric', 'min:0', 'max:1'],
            'auto_close_after_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'escalate_on_negative_sentiment' => ['required', 'boolean'],
            'allowed_actions' => ['array'],
            'allowed_actions.*' => ['string'],
        ]);

        /*
         * Seuls les outils réellement existants sont conservés :
         * une valeur inventée côté client n'a aucun effet.
         */
        $known = array_keys($this->registry->all());

        $validated['allowed_actions'] = array_values(
            array_intersect($validated['allowed_actions'] ?? [], $known)
        );

        $organization->update([
            'ai_settings' => array_merge(
                is_array($organization->ai_settings) ? $organization->ai_settings : [],
                array_filter(
                    $validated,
                    fn ($value) => $value !== null
                )
            ),
        ]);

        return back()->with('success', 'Réglages de l\'Autopilot enregistrés.');
    }

    /**
     * File des actions en attente de validation.
     */
    public function approvals(Request $request)
    {
        $organization = $this->organization($request);

        $actions = AiAction::query()
            ->where('organization_id', $organization->id)
            ->with(['client', 'conversation', 'reviewer'])
            ->when(
                $request->input('status', 'pending') !== 'all',
                fn ($query) => $query->where(
                    'status',
                    $request->input('status', 'pending')
                )
            )
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AiAction $action) => [
                'id' => $action->id,
                'tool' => $action->tool,
                'status' => $action->status,
                'input' => $action->input,
                'output' => $action->output,
                'reason' => $action->reason,
                'autopilot_level' => $action->autopilot_level,
                'client' => $action->client?->full_name,
                'conversation_id' => $action->conversation_id,
                'reviewer' => $action->reviewer?->name,
                'reviewed_at' => optional($action->reviewed_at)->toDateTimeString(),
                'created_at' => optional($action->created_at)->toDateTimeString(),
            ]);

        return Inertia::render('Autopilot/Approvals', [
            'actions' => $actions,
            'filters' => [
                'status' => $request->input('status', 'pending'),
            ],
            'counts' => [
                'pending' => AiAction::query()
                    ->where('organization_id', $organization->id)
                    ->pending()
                    ->count(),
            ],
        ]);
    }

    public function approve(Request $request, AiAction $action)
    {
        $this->authorizeAction($request, $action);

        $result = $this->executor->approve($action, $request->user());

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    public function reject(Request $request, AiAction $action)
    {
        $this->authorizeAction($request, $action);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->executor->reject(
            $action,
            $request->user(),
            $validated['reason'] ?? null
        );

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Alertes de la supervision.
     */
    public function insights(Request $request)
    {
        $organization = $this->organization($request);

        $insights = AiInsight::query()
            ->where('organization_id', $organization->id)
            ->with(['agent', 'client'])
            ->when(
                $request->input('status', 'open') === 'open',
                fn ($query) => $query->open(),
                fn ($query) => $query->where('status', $request->input('status'))
            )
            ->when(
                $request->input('type'),
                fn ($query) => $query->where('type', $request->input('type'))
            )
            ->orderByRaw(
                "FIELD(severity, 'critical', 'high', 'medium', 'low')"
            )
            ->latest('detected_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AiInsight $insight) => [
                'id' => $insight->id,
                'type' => $insight->type,
                'severity' => $insight->severity,
                'title' => $insight->title,
                'detail' => $insight->detail,
                'subject_type' => $insight->subject_type,
                'subject_id' => $insight->subject_id,
                'agent' => $insight->agent?->name,
                'client' => $insight->client?->full_name,
                'metrics' => $insight->metrics,
                'status' => $insight->status,
                'detected_at' => optional($insight->detected_at)->toDateTimeString(),
            ]);

        return Inertia::render('Autopilot/Insights', [
            'insights' => $insights,

            'filters' => [
                'status' => $request->input('status', 'open'),
                'type' => $request->input('type', ''),
            ],

            'summary' => AiInsight::query()
                ->where('organization_id', $organization->id)
                ->open()
                ->selectRaw('type, severity, COUNT(*) as total')
                ->groupBy('type', 'severity')
                ->get(),
        ]);
    }

    public function acknowledgeInsight(Request $request, AiInsight $insight)
    {
        abort_unless(
            $insight->organization_id === $request->user()->organization_id,
            403
        );

        $insight->update(['status' => 'acknowledged']);

        return back()->with('success', 'Alerte prise en compte.');
    }

    public function resolveInsight(Request $request, AiInsight $insight)
    {
        abort_unless(
            $insight->organization_id === $request->user()->organization_id,
            403
        );

        $insight->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        return back()->with('success', 'Alerte clôturée.');
    }

    /**
     * Organisation de l'utilisateur, avec contrôle du rôle.
     */
    private function organization(Request $request): Organization
    {
        $user = $request->user();

        abort_unless($user->role === 'owner', 403);

        $organization = $user->organization;

        abort_unless($organization, 404);

        return $organization;
    }

    private function authorizeAction(Request $request, AiAction $action): void
    {
        $user = $request->user();

        abort_unless($user->role === 'owner', 403);

        abort_unless(
            $action->organization_id === $user->organization_id,
            403
        );
    }
}
