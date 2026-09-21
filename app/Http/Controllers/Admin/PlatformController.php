<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AI\Usage\UsageMeter;
use App\Services\Billing\Billing;
use App\Services\OrganizationProvisioner;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Espace d'exploitation de la plateforme.
 *
 * Vue d'ensemble des entreprises clientes, des règlements et des
 * coordonnées de paiement. Réservé aux administrateurs.
 */
class PlatformController extends Controller
{
    public function __construct(
        private readonly Billing $billing,
        private readonly UsageMeter $usage,
    ) {
    }

    /**
     * Tableau de bord.
     */
    public function index(Request $request)
    {
        $organizations = Organization::query()
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(function (Organization $organization) {
                $summary = $this->usage->summary($organization);

                $subscription = $this->billing->subscription($organization);

                return [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'status' => $organization->status,
                    'plan' => $summary['plan'],
                    'agents' => $organization->users_count,

                    'messages' => [
                        'used' => $summary['ai_messages']['used'],
                        'limit' => $summary['ai_messages']['limit'],
                        'unlimited' => $summary['ai_messages']['unlimited'],
                        'ratio' => $summary['ai_messages']['ratio'],
                    ],

                    'voice_calls' => $summary['voice_calls']['used'],

                    /*
                     * Le coût est ce que cette entreprise te coûte,
                     * pas ce qu'elle te rapporte. Les deux côte à côte
                     * disent tout de suite si une formule est mal
                     * calibrée.
                     */
                    'cost' => $summary['estimated_cost'],
                    'revenue' => $subscription?->amount ?? 0,

                    'ends_at' => optional($subscription?->ends_at)->toDateString(),
                    'days_remaining' => $subscription?->daysRemaining(),
                    'created_at' => optional($organization->created_at)->toDateString(),
                ];
            });

        $active = Subscription::query()->active()->get();

        return Inertia::render('Admin/Dashboard', [
            'organizations' => $organizations,

            'totals' => [
                'organizations' => $organizations->count(),

                'paying' => $organizations
                    ->where('plan', '!=', 'free')
                    ->count(),

                /*
                 * Revenu mensuel récurrent : la somme des abonnements
                 * actifs.
                 */
                'recurring_revenue' => (int) $active->sum('amount'),

                'estimated_cost' => round($organizations->sum('cost'), 2),

                'pending_payments' => Payment::where('status', 'pending')->count(),

                'conversations_today' => Conversation::query()
                    ->whereDate('created_at', today())
                    ->count(),
            ],

            'currency' => config('ai.billing.currency', 'XOF'),

            'plans' => array_keys(config('ai.plans', [])),
        ]);
    }

    /**
     * Crée une entreprise cliente et son propriétaire.
     *
     * Sert à l'accueil d'un client que vous inscrivez vous-même, après
     * une démonstration ou un accord commercial, plutôt que de lui
     * demander de passer par le formulaire public.
     */
    public function storeOrganization(
        Request $request,
        OrganizationProvisioner $provisioner
    ) {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:120'],
            'owner_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],

            'plan' => [
                'nullable',
                Rule::in(array_keys(config('ai.plans', []))),
            ],
        ]);

        $created = $provisioner->create(
            companyName: $validated['company_name'],
            ownerName: $validated['owner_name'],
            email: $validated['email'],
            password: $validated['password'],
            phone: $validated['phone'] ?? null,
        );

        /*
         * Une formule autre que gratuite est posée directement : c'est
         * le cas d'un client qui a déjà réglé, ou d'un essai accordé.
         */
        if (!empty($validated['plan']) && $validated['plan'] !== 'free') {
            $this->billing->applyPlan(
                $created['organization'],
                $validated['plan']
            );
        }

        return back()->with(
            'success',
            $created['organization']->name . ' est créée. '
            . 'Communiquez à ' . $validated['email']
            . ' son mot de passe provisoire : il pourra le changer '
            . 'depuis son profil.'
        );
    }

    /**
     * Règlements.
     */
    public function payments(Request $request)
    {
        $payments = Payment::query()
            ->with(['organization', 'initiator'])
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
            ->through(fn (Payment $payment) => [
                'id' => $payment->id,
                'reference' => $payment->reference,
                'organization' => $payment->organization?->name,
                'organization_id' => $payment->organization_id,
                'plan' => $payment->plan,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'provider' => $payment->provider,
                'method' => $payment->method,
                'status' => $payment->status,
                'requested_by' => $payment->initiator?->name,
                'created_at' => optional($payment->created_at)->toDateTimeString(),
                'paid_at' => optional($payment->paid_at)->toDateTimeString(),
            ]);

        return Inertia::render('Admin/Payments', [
            'payments' => $payments,
            'filters' => ['status' => $request->input('status', 'pending')],
            'pending_count' => Payment::where('status', 'pending')->count(),
        ]);
    }

    /**
     * Confirme la réception d'un règlement.
     */
    public function confirmPayment(Request $request, Payment $payment)
    {
        if ($payment->status === 'paid') {
            return back()->with('warning', 'Ce règlement est déjà confirmé.');
        }

        $validated = $request->validate([
            'method' => ['nullable', 'string', 'max:50'],
        ]);

        if (!empty($validated['method'])) {
            $payment->update(['method' => $validated['method']]);
        }

        $subscription = $this->billing->confirm($payment);

        return back()->with(
            'success',
            $payment->organization->name . ' est en formule '
            . $subscription->plan . " jusqu'au "
            . $subscription->ends_at->format('d/m/Y') . '.'
        );
    }

    public function rejectPayment(Request $request, Payment $payment)
    {
        if ($payment->status === 'paid') {
            return back()->with(
                'error',
                'Un règlement confirmé ne peut pas être rejeté.'
            );
        }

        $payment->update(['status' => 'failed']);

        return back()->with('success', 'Règlement marqué comme non abouti.');
    }

    /**
     * Change la formule d'une entreprise sans passer par un paiement.
     *
     * Utile pour un geste commercial, une période d'essai prolongée ou
     * une correction.
     */
    public function changePlan(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'plan' => [
                'required',
                Rule::in(array_keys(config('ai.plans', []))),
            ],
        ]);

        $this->billing->applyPlan($organization, $validated['plan']);

        return back()->with(
            'success',
            $organization->name . ' passe en formule '
            . $validated['plan'] . '.'
        );
    }

    /**
     * Coordonnées de règlement.
     */
    public function settings(Request $request)
    {
        return Inertia::render('Admin/Settings', [
            'payment_details' => PlatformSetting::paymentDetails(),
            'provider' => config('ai.billing.provider'),
            'currency' => config('ai.billing.currency', 'XOF'),

            'plans' => collect(config('ai.plans', []))
                ->map(fn ($plan, $name) => [
                    'name' => $name,
                    'label' => $plan['label'] ?? ucfirst($name),
                    'price' => (int) ($plan['price'] ?? 0),
                ])
                ->values(),

            'admins' => User::where('is_super_admin', true)
                ->get()
                ->map(fn (User $user) => [
                    'name' => $user->name,
                    'email' => $user->email,
                ]),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'wave' => ['nullable', 'string', 'max:40'],
            'orange_money' => ['nullable', 'string', 'max:40'],
            'mtn_money' => ['nullable', 'string', 'max:40'],
            'moov_money' => ['nullable', 'string', 'max:40'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_iban' => ['nullable', 'string', 'max:60'],
            'bank_holder' => ['nullable', 'string', 'max:120'],
            'instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        PlatformSetting::put('payment_details', $validated);

        return back()->with('success', 'Coordonnées de règlement enregistrées.');
    }
}
