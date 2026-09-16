<?php

namespace App\Services\AI\Tools;

use App\Models\Call;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Ticket;
use Illuminate\Support\Str;

class GetClientProfileTool implements Tool
{
    public function name(): string
    {
        return 'get_client_profile';
    }

    public function description(): string
    {
        return "Consulte le dossier du client en cours : identité, tickets "
            . "en cours et passés, derniers appels, historique récent. "
            . "À utiliser dès que le client parle de « ma demande », "
            . "« mon dossier », « la dernière fois », ou pour vérifier "
            . "s'il a déjà signalé le même problème.";
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'email' => [
                    'type' => 'string',
                    'description' => "Email du client, uniquement s'il s'agit "
                        . "d'un autre client que celui de la conversation en cours.",
                ],
                'phone' => [
                    'type' => 'string',
                    'description' => 'Téléphone du client. Facultatif.',
                ],
            ],
            'required' => [],
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
        $client = $context->client;

        /*
         * Recherche explicite par email ou téléphone,
         * toujours limitée à l'organisation courante.
         */
        $email = trim((string) ($input['email'] ?? ''));

        $phone = trim((string) ($input['phone'] ?? ''));

        if ($email || $phone) {
            $client = Client::query()
                ->where('organization_id', $context->organization->id)
                ->when($email, fn ($query) => $query->where('email', $email))
                ->when(!$email && $phone, fn ($query) => $query->where('phone', $phone))
                ->first();
        }

        if (!$client) {
            return [
                'found' => false,
                'message' => 'Aucun dossier client ne correspond.',
            ];
        }

        $tickets = Ticket::query()
            ->where('organization_id', $context->organization->id)
            ->where('client_id', $client->id)
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Ticket $ticket) => [
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'category' => $ticket->category,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'created_at' => optional($ticket->created_at)->toDateTimeString(),
                'resolved_at' => optional($ticket->resolved_at)->toDateTimeString(),
            ])
            ->values()
            ->all();

        $calls = Call::query()
            ->where('organization_id', $context->organization->id)
            ->where('client_id', $client->id)
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Call $call) => [
                'type' => $call->type,
                'status' => $call->status,
                'duration_seconds' => $call->duration,
                'reason' => $call->reason,
                'started_at' => optional($call->started_at)->toDateTimeString(),
            ])
            ->values()
            ->all();

        $conversations = Conversation::query()
            ->where('organization_id', $context->organization->id)
            ->where('client_id', $client->id)
            ->when(
                $context->conversation,
                fn ($query) => $query->where('id', '!=', $context->conversation->id)
            )
            ->latest('last_message_at')
            ->limit(5)
            ->get()
            ->map(fn (Conversation $conversation) => [
                'subject' => $conversation->subject,
                'channel' => $conversation->channel,
                'status' => $conversation->status,
                'summary' => Str::limit((string) $conversation->ai_summary, 300),
                'last_message_at' => optional($conversation->last_message_at)->toDateTimeString(),
            ])
            ->values()
            ->all();

        return [
            'found' => true,
            'client' => [
                'full_name' => $client->full_name,
                'email' => $client->email,
                'phone' => $client->phone,
                'company' => $client->company,
                'city' => $client->city,
                'country' => $client->country,
                'status' => $client->status,
                'client_since' => optional($client->created_at)->toDateString(),
                'internal_notes' => Str::limit((string) $client->notes, 500),
            ],
            'open_tickets_count' => collect($tickets)
                ->whereIn('status', ['open', 'pending', 'in_progress'])
                ->count(),
            'tickets' => $tickets,
            'recent_calls' => $calls,
            'past_conversations' => $conversations,
        ];
    }
}
