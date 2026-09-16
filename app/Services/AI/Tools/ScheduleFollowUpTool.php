<?php

namespace App\Services\AI\Tools;

use App\Models\FollowUp;
use App\Models\Ticket;
use Carbon\Carbon;

class ScheduleFollowUpTool implements Tool
{
    public function name(): string
    {
        return 'schedule_follow_up';
    }

    public function description(): string
    {
        return "Programme une relance automatique vers le client. "
            . "À utiliser quand une vérification prend du temps : "
            . "« je reviens vers vous demain avec le statut de votre livraison », "
            . "ou pour confirmer plus tard que le problème est bien réglé. "
            . "Indiquer un délai réaliste en heures.";
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'in_hours' => [
                    'type' => 'number',
                    'description' => 'Délai avant la relance, en heures. '
                        . 'Entre 1 et 168 (une semaine).',
                ],
                'instruction' => [
                    'type' => 'string',
                    'description' => "Ce qu'il faudra vérifier et dire au client "
                        . "au moment de la relance.",
                ],
                'ticket_number' => [
                    'type' => 'string',
                    'description' => 'Ticket concerné, si la relance en dépend. Facultatif.',
                ],
            ],
            'required' => ['in_hours', 'instruction'],
        ];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function isSafeInAssistMode(): bool
    {
        return false;
    }

    public function handle(array $input, ToolContext $context): array
    {
        if (!$context->client) {
            return [
                'success' => false,
                'message' => 'Aucun client identifié : relance impossible.',
            ];
        }

        $hours = (float) ($input['in_hours'] ?? 24);

        $hours = max(1, min(168, $hours));

        $runAt = Carbon::now()->addMinutes((int) round($hours * 60));

        $ticketId = null;

        if (!empty($input['ticket_number'])) {
            $ticketId = Ticket::query()
                ->where('organization_id', $context->organization->id)
                ->where('ticket_number', trim((string) $input['ticket_number']))
                ->value('id');
        }

        $followUp = FollowUp::create([
            'organization_id' => $context->organization->id,
            'client_id' => $context->client->id,
            'conversation_id' => $context->conversation?->id,
            'ticket_id' => $ticketId,
            'instruction' => trim((string) $input['instruction']),
            'channel' => $context->channel,
            'run_at' => $runAt,
            'status' => 'pending',
            'created_by_ai' => true,
        ]);

        $context->recordEffect('follow_up_id', $followUp->id);

        return [
            'success' => true,
            'run_at' => $runAt->toDateTimeString(),
            'message' => 'Relance programmée. Annoncer ce délai au client.',
        ];
    }
}
