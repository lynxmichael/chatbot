<?php

namespace App\Services\AI\Tools;

use App\Models\Ticket;
use Illuminate\Support\Str;

class GetTicketStatusTool implements Tool
{
    public function name(): string
    {
        return 'get_ticket_status';
    }

    public function description(): string
    {
        return "Consulte l'état d'un ticket existant à partir de son numéro "
            . "(format TCK-XXXXXXXX). À utiliser quand le client demande "
            . "où en est sa réclamation ou sa demande.";
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'ticket_number' => [
                    'type' => 'string',
                    'description' => 'Numéro du ticket communiqué par le client.',
                ],
            ],
            'required' => ['ticket_number'],
        ];
    }

    public function isWrite(): bool
    {
        return false;
    }

    public function isSafeInAssistMode(): bool
    {
        return true;
    }

    public function handle(array $input, ToolContext $context): array
    {
        $number = trim((string) ($input['ticket_number'] ?? ''));

        $ticket = Ticket::query()
            ->where('organization_id', $context->organization->id)
            ->where('ticket_number', $number)
            ->first();

        if (!$ticket) {
            return [
                'found' => false,
                'message' => 'Aucun ticket ne porte ce numéro.',
            ];
        }

        /*
         * Sécurité : un client ne doit pouvoir consulter
         * que ses propres tickets.
         */
        if (
            $context->client
            && $ticket->client_id !== $context->client->id
        ) {
            return [
                'found' => false,
                'message' => 'Ce ticket n\'appartient pas au client en cours. '
                    . 'Ne communiquer aucune information à son sujet.',
            ];
        }

        return [
            'found' => true,
            'ticket' => [
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'category' => $ticket->category,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'assigned' => (bool) $ticket->assigned_to,
                'created_at' => optional($ticket->created_at)->toDateTimeString(),
                'sla_due_at' => optional($ticket->sla_due_at)->toDateTimeString(),
                'resolution' => Str::limit((string) $ticket->resolution, 500),
                'resolved_at' => optional($ticket->resolved_at)->toDateTimeString(),
            ],
        ];
    }
}
