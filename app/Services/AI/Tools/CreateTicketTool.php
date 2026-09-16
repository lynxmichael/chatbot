<?php

namespace App\Services\AI\Tools;

use App\Models\Ticket;
use App\Services\AI\Support\AgentRouter;
use App\Services\AI\Support\SlaCalculator;
use Illuminate\Support\Str;

class CreateTicketTool implements Tool
{
    public function __construct(
        private readonly AgentRouter $router,
        private readonly SlaCalculator $sla
    ) {
    }

    public function name(): string
    {
        return 'create_ticket';
    }

    public function description(): string
    {
        return "Crée un ticket pour une demande qui ne peut pas être résolue "
            . "immédiatement ou qui doit être suivie : réclamation, problème "
            . "de livraison, incident technique, demande de remboursement. "
            . "La catégorie, la priorité, l'agent responsable et le SLA sont "
            . "déterminés à partir des informations fournies. "
            . "Ne pas créer deux tickets pour la même demande.";
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'subject' => [
                    'type' => 'string',
                    'description' => 'Titre court et précis de la demande.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => "Description complète du problème, rédigée "
                        . "pour l'agent : contexte, éléments fournis par le client, "
                        . "vérifications déjà effectuées.",
                ],
                'category' => [
                    'type' => 'string',
                    'enum' => [
                        'facturation',
                        'livraison',
                        'technique',
                        'reclamation',
                        'remboursement',
                        'commande',
                        'compte',
                        'general',
                    ],
                    'description' => 'Catégorie de la demande.',
                ],
                'priority' => [
                    'type' => 'string',
                    'enum' => ['low', 'normal', 'high', 'urgent'],
                    'description' => "Priorité. « urgent » uniquement en cas de "
                        . "blocage total, de risque financier immédiat ou de client "
                        . "très mécontent.",
                ],
                'client_impact' => [
                    'type' => 'string',
                    'description' => "Conséquence concrète pour le client. Facultatif.",
                ],
            ],
            'required' => ['subject', 'description', 'category', 'priority'],
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
                'message' => 'Aucun client identifié : impossible de créer un ticket.',
            ];
        }

        $category = $this->normalizeCategory($input['category'] ?? 'general');

        $priority = $this->normalizePriority($input['priority'] ?? 'normal');

        /*
         * Anti-doublon : un ticket actif existe déjà pour cette conversation.
         */
        if ($context->conversation) {
            $existing = Ticket::query()
                ->where('organization_id', $context->organization->id)
                ->where('conversation_id', $context->conversation->id)
                ->whereIn('status', ['open', 'pending', 'in_progress'])
                ->latest('id')
                ->first();

            if ($existing) {
                return [
                    'success' => true,
                    'already_exists' => true,
                    'ticket_number' => $existing->ticket_number,
                    'message' => 'Un ticket actif existe déjà pour cette demande. '
                        . 'Communiquer ce numéro au client plutôt que d\'en créer un nouveau.',
                ];
            }
        }

        $assignedTo = $this->router->pick(
            $context->organization->id,
            $category,
            $priority
        );

        $slaDueAt = $this->sla->firstResponseDueAt($priority);

        $channel = in_array(
            $context->channel,
            ['web', 'widget', 'whatsapp', 'email', 'phone'],
            true
        )
            ? $context->channel
            : 'widget';

        $description = trim((string) ($input['description'] ?? ''));

        if (!empty($input['client_impact'])) {
            $description .= "\n\nImpact pour le client : "
                . trim((string) $input['client_impact']);
        }

        $description .= "\n\n--\nTicket qualifié automatiquement par l'assistant IA.";

        $ticket = Ticket::create([
            'organization_id' => $context->organization->id,
            'client_id' => $context->client->id,
            'conversation_id' => $context->conversation?->id,
            'assigned_to' => $assignedTo,
            'ticket_number' => $this->generateNumber(),
            'subject' => Str::limit(
                trim((string) ($input['subject'] ?? 'Demande client')),
                180,
                ''
            ),
            'description' => $description,
            'category' => $category,
            'status' => 'open',
            'priority' => $priority,
            'channel' => $channel,
            'sla_due_at' => $slaDueAt,
        ]);

        $context->recordEffect('ticket_id', $ticket->id);

        $context->recordEffect('ticket_number', $ticket->ticket_number);

        return [
            'success' => true,
            'ticket_number' => $ticket->ticket_number,
            'category' => $category,
            'priority' => $priority,
            'assigned' => (bool) $assignedTo,
            'sla_due_at' => $slaDueAt->toDateTimeString(),
            'message' => 'Ticket créé. Communiquer le numéro au client '
                . 'et indiquer le délai de traitement prévu.',
        ];
    }

    private function normalizeCategory(string $category): string
    {
        $allowed = config('ai.categories', ['general']);

        $category = Str::of($category)->lower()->ascii()->trim()->toString();

        return in_array($category, $allowed, true)
            ? $category
            : 'general';
    }

    private function normalizePriority(string $priority): string
    {
        $priority = Str::lower(trim($priority));

        return in_array($priority, ['low', 'normal', 'high', 'urgent'], true)
            ? $priority
            : 'normal';
    }

    private function generateNumber(): string
    {
        do {
            $number = 'TCK-' . Str::upper(Str::random(8));
        } while (
            Ticket::query()
                ->where('ticket_number', $number)
                ->exists()
        );

        return $number;
    }
}
