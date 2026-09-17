<?php

namespace App\Services\AI\Tools;

use App\Models\Ticket;
use App\Models\FollowUp;
use App\Services\AI\Support\AgentRouter;
use App\Services\AI\Support\BusinessHours;
use App\Services\AI\Support\SlaCalculator;
use Illuminate\Support\Str;

class EscalateToHumanTool implements Tool
{
    public function __construct(
        private readonly AgentRouter $router,
        private readonly SlaCalculator $sla,
        private readonly BusinessHours $hours
    ) {
    }

    public function name(): string
    {
        return 'escalate_to_human';
    }

    public function description(): string
    {
        return "Transfère la conversation à un agent humain et prépare son "
            . "dossier. À utiliser quand : le client le demande, la demande "
            . "dépasse tes capacités, elle exige une décision commerciale, "
            . "un geste financier, une vérification d'identité, ou quand le "
            . "client est visiblement mécontent. "
            . "Après ce transfert, annonce au client qu'un conseiller prend le relais.";
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reason' => [
                    'type' => 'string',
                    'description' => 'Motif du transfert, en une phrase.',
                ],
                'summary' => [
                    'type' => 'string',
                    'description' => "Dossier pour l'agent : demande du client, "
                        . "informations déjà collectées, vérifications déjà faites, "
                        . "ce qu'il reste à faire.",
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
                    'description' => 'Priorité du transfert.',
                ],
                'create_ticket' => [
                    'type' => 'boolean',
                    'description' => "true si un ticket doit être ouvert pour suivre "
                        . "la demande. Par défaut true.",
                ],
            ],
            'required' => ['reason', 'summary'],
        ];
    }

    public function isWrite(): bool
    {
        return true;
    }

    /**
     * Passer la main à un humain est toujours une action sûre :
     * elle n'engage rien vis-à-vis du client et protège la relation.
     */
    public function isSafeInAssistMode(): bool
    {
        return true;
    }

    public function handle(array $input, ToolContext $context): array
    {
        $conversation = $context->conversation;

        if (!$conversation) {
            return [
                'success' => false,
                'message' => 'Aucune conversation à transférer.',
            ];
        }

        $category = $input['category'] ?? 'general';

        $priority = $input['priority'] ?? 'normal';

        $reason = trim((string) ($input['reason'] ?? 'Transfert demandé'));

        $summary = trim((string) ($input['summary'] ?? ''));

        /*
         * Personne ne peut décrocher : service fermé, ou tous les
         * conseillers déjà occupés. Le transfert devient un rappel
         * programmé, et l'IA doit l'annoncer comme tel plutôt que de
         * promettre une mise en relation qui n'arrivera pas.
         */
        $reachable = $this->hours->hasReachableAgent($context->organization);

        $agentId = $this->router->pick(
            $context->organization->id,
            $category,
            $priority
        );

        /*
         * La conversation repasse en traitement humain :
         * l'IA se met en retrait pour ne pas répondre par-dessus l'agent.
         */
        $conversation->update([
            'status' => 'open',
            'ai_enabled' => false,
            'assigned_to' => $agentId,
            'priority' => $priority,
            'ai_summary' => $summary ?: $conversation->ai_summary,
        ]);

        $ticketNumber = null;

        $shouldCreateTicket = $input['create_ticket'] ?? true;

        if ($shouldCreateTicket && $context->client) {
            $existing = Ticket::query()
                ->where('organization_id', $context->organization->id)
                ->where('conversation_id', $conversation->id)
                ->whereIn('status', ['open', 'pending', 'in_progress'])
                ->latest('id')
                ->first();

            if ($existing) {
                $existing->update([
                    'assigned_to' => $existing->assigned_to ?: $agentId,
                    'priority' => $priority,
                ]);

                $ticketNumber = $existing->ticket_number;

                $context->recordEffect('ticket_id', $existing->id);
            } else {
                $ticket = Ticket::create([
                    'organization_id' => $context->organization->id,
                    'client_id' => $context->client->id,
                    'conversation_id' => $conversation->id,
                    'assigned_to' => $agentId,
                    'ticket_number' => $this->generateNumber(),
                    'subject' => Str::limit(
                        $conversation->subject ?: $reason,
                        180,
                        ''
                    ),
                    'description' => "Motif du transfert : " . $reason
                        . "\n\nDossier préparé par l'assistant IA :\n"
                        . ($summary ?: 'Aucun élément complémentaire.'),
                    'category' => in_array($category, config('ai.categories', []), true)
                        ? $category
                        : 'general',
                    'status' => 'open',
                    'priority' => $priority,
                    'channel' => in_array(
                        $context->channel,
                        ['web', 'widget', 'whatsapp', 'email', 'phone'],
                        true
                    )
                        ? $context->channel
                        : 'widget',
                    'sla_due_at' => $this->sla->firstResponseDueAt($priority),
                ]);

                $ticketNumber = $ticket->ticket_number;

                $context->recordEffect('ticket_id', $ticket->id);
            }
        }

        $context->recordEffect('escalated', true);

        $context->recordEffect('escalation_reason', $reason);

        $context->recordEffect('assigned_to', $agentId);

        if ($ticketNumber) {
            $context->recordEffect('ticket_number', $ticketNumber);
        }

        if ($reachable) {
            return [
                'success' => true,
                'transfer' => 'immediate',
                'ticket_number' => $ticketNumber,
                'message' => 'Un conseiller est joignable : la mise en '
                    . 'relation se fait maintenant. Annonce-le au client '
                    . 'en une phrase courte.',
            ];
        }

        /*
         * Rappel programmé à la réouverture. Le client repart avec une
         * heure, pas avec « nous reviendrons vers vous ».
         */
        $callbackAt = $this->hours->nextOpening($context->organization);

        $context->recordEffect('deferred', true);

        $context->recordEffect('callback_at', $callbackAt->toDateTimeString());

        if ($context->client) {
            FollowUp::create([
                'organization_id' => $context->organization->id,
                'client_id' => $context->client->id,
                'conversation_id' => $conversation->id,
                'ticket_id' => $context->effects['ticket_id'] ?? null,
                'instruction' => 'Rappeler le client : ' . $reason
                    . ' — demande reçue en dehors des heures d\'ouverture '
                    . 'ou alors que tous les conseillers étaient occupés.',
                'channel' => $context->channel,
                'run_at' => $callbackAt,
                'status' => 'pending',
                'created_by_ai' => true,
            ]);
        }

        return [
            'success' => true,
            'transfer' => 'deferred',
            'ticket_number' => $ticketNumber,
            'callback_at' => $this->hours->nextOpeningInWords(
                $context->organization
            ),
            'message' => 'AUCUN conseiller ne peut décrocher maintenant. '
                . 'Ne promets pas de mise en relation. Rassemble les '
                . 'informations utiles, confirme que la demande est '
                . 'enregistrée, et annonce un rappel '
                . $this->hours->nextOpeningInWords($context->organization)
                . '. Donne le numéro de ticket s\'il existe.',
        ];
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
