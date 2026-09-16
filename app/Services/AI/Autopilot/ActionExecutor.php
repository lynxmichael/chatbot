<?php

namespace App\Services\AI\Autopilot;

use App\Models\AiAction;
use App\Models\User;
use App\Services\AI\Tools\ToolContext;
use App\Services\AI\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Exécute une action que l'IA avait mise en attente, après validation
 * par un humain.
 *
 * La politique Autopilot n'est volontairement pas rejouée ici : la
 * décision humaine fait autorité. C'est tout l'intérêt du mode
 * « assist » — l'IA propose, un responsable tranche.
 */
class ActionExecutor
{
    public function __construct(
        private readonly ToolRegistry $registry
    ) {
    }

    public function approve(AiAction $action, User $approver): array
    {
        if ($action->status !== 'pending') {
            return [
                'success' => false,
                'message' => 'Cette action a déjà été traitée.',
            ];
        }

        $tool = $this->registry->get($action->tool);

        if (!$tool) {
            $action->update([
                'status' => 'failed',
                'reason' => 'Outil inconnu : ' . $action->tool,
                'reviewed_by' => $approver->id,
                'reviewed_at' => now(),
            ]);

            return [
                'success' => false,
                'message' => 'Outil introuvable.',
            ];
        }

        $action->loadMissing('organization', 'conversation', 'client', 'message');

        if (!$action->organization) {
            return [
                'success' => false,
                'message' => 'Organisation introuvable.',
            ];
        }

        $context = new ToolContext(
            organization: $action->organization,
            policy: AutopilotPolicy::forOrganization($action->organization),
            conversation: $action->conversation,
            client: $action->client,
            message: $action->message,
            channel: $action->conversation?->channel ?: 'widget',
        );

        try {
            $output = $tool->handle($action->input ?? [], $context);
        } catch (Throwable $exception) {
            $action->update([
                'status' => 'failed',
                'reason' => mb_substr($exception->getMessage(), 0, 1000),
                'reviewed_by' => $approver->id,
                'reviewed_at' => now(),
            ]);

            Log::error(
                "Échec de l'exécution d'une action validée.",
                [
                    'ai_action_id' => $action->id,
                    'tool' => $action->tool,
                    'error' => $exception->getMessage(),
                ]
            );

            return [
                'success' => false,
                'message' => "L'action a échoué : " . $exception->getMessage(),
            ];
        }

        $action->update([
            'status' => 'approved',
            'output' => $output,
            'ticket_id' => $context->effects['ticket_id'] ?? $action->ticket_id,
            'reviewed_by' => $approver->id,
            'reviewed_at' => now(),
            'executed_at' => now(),
        ]);

        Log::info(
            'Action IA validée et exécutée.',
            [
                'ai_action_id' => $action->id,
                'tool' => $action->tool,
                'approved_by' => $approver->id,
            ]
        );

        return [
            'success' => true,
            'output' => $output,
            'message' => 'Action exécutée.',
        ];
    }

    public function reject(AiAction $action, User $approver, ?string $reason = null): array
    {
        if ($action->status !== 'pending') {
            return [
                'success' => false,
                'message' => 'Cette action a déjà été traitée.',
            ];
        }

        $action->update([
            'status' => 'rejected',
            'reason' => $reason ?: 'Refusée par un responsable.',
            'reviewed_by' => $approver->id,
            'reviewed_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'Action refusée.',
        ];
    }
}
