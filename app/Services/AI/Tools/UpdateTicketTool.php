<?php

namespace App\Services\AI\Tools;

use App\Models\Ticket;

class UpdateTicketTool implements Tool
{
    public function name(): string
    {
        return 'update_ticket';
    }

    public function description(): string
    {
        return "Met à jour un ticket existant : changer le statut, ajuster la "
            . "priorité, enregistrer la résolution. À utiliser notamment quand "
            . "le client confirme que son problème est réglé : le ticket peut "
            . "alors être passé en « resolved » avec la solution apportée.";
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'ticket_number' => [
                    'type' => 'string',
                    'description' => 'Numéro du ticket à mettre à jour.',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['open', 'pending', 'in_progress', 'resolved', 'closed'],
                    'description' => 'Nouveau statut. Facultatif.',
                ],
                'priority' => [
                    'type' => 'string',
                    'enum' => ['low', 'normal', 'high', 'urgent'],
                    'description' => 'Nouvelle priorité. Facultatif.',
                ],
                'resolution' => [
                    'type' => 'string',
                    'description' => 'Description de la solution apportée. '
                        . 'Obligatoire pour passer en « resolved ».',
                ],
            ],
            'required' => ['ticket_number'],
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
        $ticket = Ticket::query()
            ->where('organization_id', $context->organization->id)
            ->where('ticket_number', trim((string) ($input['ticket_number'] ?? '')))
            ->first();

        if (!$ticket) {
            return [
                'success' => false,
                'message' => 'Ticket introuvable.',
            ];
        }

        if (
            $context->client
            && $ticket->client_id !== $context->client->id
        ) {
            return [
                'success' => false,
                'message' => 'Ce ticket appartient à un autre client.',
            ];
        }

        $status = $input['status'] ?? null;

        $resolution = trim((string) ($input['resolution'] ?? ''));

        if ($status === 'resolved' && !$resolution) {
            return [
                'success' => false,
                'message' => 'Une résolution doit être fournie '
                    . 'pour passer le ticket en « resolved ».',
            ];
        }

        $changes = [];

        if ($status) {
            $changes['status'] = $status;

            if ($status === 'resolved') {
                $changes['resolved_at'] = now();
            }

            if ($status === 'closed') {
                $changes['closed_at'] = now();
            }
        }

        if (!empty($input['priority'])) {
            $changes['priority'] = $input['priority'];
        }

        if ($resolution) {
            $changes['resolution'] = $resolution;
        }

        if (empty($changes)) {
            return [
                'success' => false,
                'message' => 'Aucune modification demandée.',
            ];
        }

        $ticket->update($changes);

        $context->recordEffect('ticket_id', $ticket->id);

        return [
            'success' => true,
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
        ];
    }
}
