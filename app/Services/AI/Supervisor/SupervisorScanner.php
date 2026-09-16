<?php

namespace App\Services\AI\Supervisor;

use App\Models\AiInsight;
use App\Models\Call;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AI\Support\AgentRouter;
use App\Services\AI\Support\SlaCalculator;
use Illuminate\Support\Str;

/**
 * Surveillance du service client.
 *
 * La détection est volontairement déterministe : ce sont des règles
 * sur les données, pas des suppositions du modèle. Une alerte « SLA
 * dépassé » doit être vraie à 100 %, sinon les agents cessent de
 * les lire. Le sentiment, lui, vient de l'analyse déjà faite par l'IA
 * pendant les conversations.
 */
class SupervisorScanner
{
    public function __construct(
        private readonly SlaCalculator $sla,
        private readonly AgentRouter $router,
    ) {
    }

    /**
     * Analyse une organisation et retourne le nombre d'alertes actives.
     */
    public function scan(Organization $organization): array
    {
        $found = [];

        foreach ([
            'slaBreached',
            'slaAtRisk',
            'staleTickets',
            'unansweredClients',
            'unhappyClients',
            'missedCalls',
            'repeatedRequests',
            'overloadedAgents',
            'unassignedTickets',
        ] as $check) {
            $found = array_merge($found, $this->{$check}($organization));
        }

        /*
         * Les alertes qui ne sont plus vraies sont refermées
         * automatiquement : un ticket enfin traité ne doit pas
         * rester signalé.
         */
        $this->closeResolved($organization, $found);

        return $found;
    }

    /**
     * SLA de première réponse dépassé.
     */
    private function slaBreached(Organization $organization): array
    {
        $tickets = Ticket::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', ['open', 'pending', 'in_progress'])
            ->whereNull('first_response_at')
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->with('assignedAgent')
            ->limit(200)
            ->get();

        $created = [];

        foreach ($tickets as $ticket) {
            $late = (int) abs(now()->diffInMinutes($ticket->sla_due_at));

            $created[] = $this->record(
                $organization,
                fingerprint: 'sla_breached:ticket:' . $ticket->id,
                type: 'sla_breached',
                severity: $ticket->priority === 'urgent' ? 'critical' : 'high',
                title: 'SLA dépassé — ' . $ticket->ticket_number,
                detail: 'Aucune première réponse sur « ' . $ticket->subject
                    . ' ». Échéance dépassée de ' . $this->humanizeMinutes($late) . '.',
                subjectType: 'ticket',
                subjectId: $ticket->id,
                userId: $ticket->assigned_to,
                clientId: $ticket->client_id,
                metrics: [
                    'minutes_late' => $late,
                    'priority' => $ticket->priority,
                    'agent' => $ticket->assignedAgent?->name,
                ],
            );
        }

        return $created;
    }

    /**
     * SLA proche de l'expiration : plus de 80 % du délai consommé.
     */
    private function slaAtRisk(Organization $organization): array
    {
        $tickets = Ticket::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', ['open', 'pending', 'in_progress'])
            ->whereNull('first_response_at')
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '>=', now())
            ->with('assignedAgent')
            ->limit(200)
            ->get();

        $created = [];

        foreach ($tickets as $ticket) {
            $ratio = $this->sla->consumedRatio(
                $ticket->created_at,
                $ticket->sla_due_at
            );

            if ($ratio === null || $ratio < 0.8) {
                continue;
            }

            $remaining = (int) abs(now()->diffInMinutes($ticket->sla_due_at));

            $created[] = $this->record(
                $organization,
                fingerprint: 'sla_at_risk:ticket:' . $ticket->id,
                type: 'sla_at_risk',
                severity: 'medium',
                title: 'SLA bientôt dépassé — ' . $ticket->ticket_number,
                detail: 'Il reste ' . $this->humanizeMinutes($remaining)
                    . ' pour répondre à « ' . $ticket->subject . ' ».',
                subjectType: 'ticket',
                subjectId: $ticket->id,
                userId: $ticket->assigned_to,
                clientId: $ticket->client_id,
                metrics: [
                    'consumed_ratio' => $ratio,
                    'minutes_remaining' => $remaining,
                    'agent' => $ticket->assignedAgent?->name,
                ],
            );
        }

        return $created;
    }

    /**
     * Tickets oubliés : ouverts et sans aucune mise à jour depuis 48 h.
     */
    private function staleTickets(Organization $organization): array
    {
        $tickets = Ticket::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', ['open', 'pending', 'in_progress'])
            ->where('updated_at', '<=', now()->subHours(48))
            ->with('assignedAgent')
            ->limit(200)
            ->get();

        $created = [];

        foreach ($tickets as $ticket) {
            $days = (int) abs(now()->diffInDays($ticket->updated_at));

            $created[] = $this->record(
                $organization,
                fingerprint: 'ticket_stale:ticket:' . $ticket->id,
                type: 'ticket_stale',
                severity: $days >= 5 ? 'high' : 'medium',
                title: 'Ticket sans activité — ' . $ticket->ticket_number,
                detail: 'Aucune mise à jour depuis ' . max(2, $days) . ' jours sur « '
                    . $ticket->subject . ' ».',
                subjectType: 'ticket',
                subjectId: $ticket->id,
                userId: $ticket->assigned_to,
                clientId: $ticket->client_id,
                metrics: [
                    'days_idle' => $days,
                    'agent' => $ticket->assignedAgent?->name,
                ],
            );
        }

        return $created;
    }

    /**
     * Clients qui attendent : le dernier message est le leur,
     * l'IA est désactivée, et personne n'a répondu depuis 2 h.
     */
    private function unansweredClients(Organization $organization): array
    {
        $conversations = Conversation::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', ['open', 'pending'])
            ->where('ai_enabled', false)
            ->where('last_message_at', '<=', now()->subHours(2))
            ->with('client', 'assignedAgent')
            ->limit(200)
            ->get();

        $created = [];

        foreach ($conversations as $conversation) {
            $lastSender = Message::query()
                ->where('conversation_id', $conversation->id)
                ->latest('created_at')
                ->latest('id')
                ->value('sender_type');

            if ($lastSender !== 'client') {
                continue;
            }

            $hours = (int) abs(now()->diffInHours($conversation->last_message_at));

            $created[] = $this->record(
                $organization,
                fingerprint: 'client_waiting:conversation:' . $conversation->id,
                type: 'client_waiting',
                severity: $hours >= 24 ? 'high' : 'medium',
                title: 'Client sans réponse depuis ' . $this->humanizeHours($hours),
                detail: ($conversation->client?->full_name ?: 'Un client')
                    . ' attend une réponse sur « '
                    . ($conversation->subject ?: 'sa demande') . ' ».',
                subjectType: 'conversation',
                subjectId: $conversation->id,
                userId: $conversation->assigned_to,
                clientId: $conversation->client_id,
                metrics: [
                    'hours_waiting' => $hours,
                    'agent' => $conversation->assignedAgent?->name,
                ],
            );
        }

        return $created;
    }

    /**
     * Clients mécontents détectés par l'analyse IA.
     */
    private function unhappyClients(Organization $organization): array
    {
        $conversations = Conversation::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', ['open', 'pending'])
            ->whereIn('ai_sentiment', ['negative', 'angry'])
            ->where('ai_last_run_at', '>=', now()->subDays(3))
            ->with('client', 'assignedAgent')
            ->limit(100)
            ->get();

        $created = [];

        foreach ($conversations as $conversation) {
            $created[] = $this->record(
                $organization,
                fingerprint: 'unhappy_client:conversation:' . $conversation->id,
                type: 'unhappy_client',
                severity: $conversation->ai_sentiment === 'angry' ? 'high' : 'medium',
                title: 'Client mécontent — '
                    . ($conversation->client?->full_name ?: 'client non identifié'),
                detail: Str::limit(
                    $conversation->ai_summary ?: 'Insatisfaction détectée pendant l\'échange.',
                    400
                ),
                subjectType: 'conversation',
                subjectId: $conversation->id,
                userId: $conversation->assigned_to,
                clientId: $conversation->client_id,
                metrics: [
                    'sentiment' => $conversation->ai_sentiment,
                    'intent' => $conversation->ai_intent,
                    'agent' => $conversation->assignedAgent?->name,
                ],
            );
        }

        return $created;
    }

    /**
     * Appels manqués sans rappel dans les 2 heures.
     */
    private function missedCalls(Organization $organization): array
    {
        $calls = Call::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'missed')
            ->where('started_at', '>=', now()->subDays(3))
            ->where('started_at', '<=', now()->subHours(2))
            ->with('client')
            ->limit(100)
            ->get();

        $created = [];

        foreach ($calls as $call) {
            /*
             * Un rappel sortant postérieur signifie que l'appel
             * a bien été traité.
             */
            $calledBack = Call::query()
                ->where('organization_id', $organization->id)
                ->where('client_id', $call->client_id)
                ->where('type', 'outgoing')
                ->where('status', 'answered')
                ->where('started_at', '>', $call->started_at)
                ->exists();

            if ($calledBack) {
                continue;
            }

            $created[] = $this->record(
                $organization,
                fingerprint: 'missed_call:call:' . $call->id,
                type: 'missed_call',
                severity: 'medium',
                title: 'Appel manqué non rappelé',
                detail: ($call->client?->full_name ?: $call->phone)
                    . ' a appelé le '
                    . optional($call->started_at)->format('d/m/Y à H:i')
                    . ' sans être rappelé.',
                subjectType: 'call',
                subjectId: $call->id,
                clientId: $call->client_id,
                metrics: [
                    'phone' => $call->phone,
                    'reason' => $call->reason,
                ],
            );
        }

        return $created;
    }

    /**
     * Demandes répétitives : un même client revient plusieurs fois
     * sur la même catégorie en 30 jours. Signe d'un problème de fond.
     */
    private function repeatedRequests(Organization $organization): array
    {
        $rows = Ticket::query()
            ->where('organization_id', $organization->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('client_id, category, COUNT(*) as total')
            ->groupBy('client_id', 'category')
            ->havingRaw('COUNT(*) >= 3')
            ->limit(100)
            ->get();

        $created = [];

        foreach ($rows as $row) {
            $client = \App\Models\Client::find($row->client_id);

            $created[] = $this->record(
                $organization,
                fingerprint: 'repeated_requests:' . $row->client_id . ':' . $row->category,
                type: 'repeated_requests',
                severity: $row->total >= 5 ? 'high' : 'medium',
                title: 'Demandes répétées — ' . $row->category,
                detail: ($client?->full_name ?: 'Un client') . ' a ouvert '
                    . $row->total . ' tickets « ' . $row->category
                    . ' » en 30 jours. Le problème de fond n\'est peut-être pas réglé.',
                subjectType: 'client',
                subjectId: $row->client_id,
                clientId: $row->client_id,
                metrics: [
                    'category' => $row->category,
                    'tickets_count' => (int) $row->total,
                ],
            );
        }

        return $created;
    }

    /**
     * Agents dont la charge dépasse leur capacité déclarée.
     */
    private function overloadedAgents(Organization $organization): array
    {
        $load = $this->router->openTicketsPerAgent($organization->id);

        if (empty($load)) {
            return [];
        }

        $agents = User::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', array_keys($load))
            ->get();

        $created = [];

        foreach ($agents as $agent) {
            $open = (int) ($load[$agent->id] ?? 0);

            $capacity = (int) ($agent->max_open_tickets ?: 15);

            if ($open <= $capacity) {
                continue;
            }

            $created[] = $this->record(
                $organization,
                fingerprint: 'agent_overloaded:agent:' . $agent->id,
                type: 'agent_overloaded',
                severity: $open >= $capacity * 1.5 ? 'high' : 'medium',
                title: 'Agent surchargé — ' . $agent->name,
                detail: $agent->name . ' a ' . $open . ' tickets ouverts '
                    . 'pour une capacité de ' . $capacity . '.',
                subjectType: 'agent',
                subjectId: $agent->id,
                userId: $agent->id,
                metrics: [
                    'open_tickets' => $open,
                    'capacity' => $capacity,
                ],
            );
        }

        return $created;
    }

    /**
     * Tickets sans responsable : personne ne les traitera.
     */
    private function unassignedTickets(Organization $organization): array
    {
        $tickets = Ticket::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', ['open', 'pending'])
            ->whereNull('assigned_to')
            ->where('created_at', '<=', now()->subMinutes(30))
            ->limit(100)
            ->get();

        $created = [];

        foreach ($tickets as $ticket) {
            $created[] = $this->record(
                $organization,
                fingerprint: 'ticket_unassigned:ticket:' . $ticket->id,
                type: 'ticket_unassigned',
                severity: $ticket->priority === 'urgent' ? 'high' : 'medium',
                title: 'Ticket sans responsable — ' . $ticket->ticket_number,
                detail: 'Aucun agent n\'est affecté à « ' . $ticket->subject . ' ».',
                subjectType: 'ticket',
                subjectId: $ticket->id,
                clientId: $ticket->client_id,
                metrics: [
                    'priority' => $ticket->priority,
                    'category' => $ticket->category,
                ],
            );
        }

        return $created;
    }

    /**
     * Crée ou met à jour une alerte.
     */
    private function record(
        Organization $organization,
        string $fingerprint,
        string $type,
        string $severity,
        string $title,
        ?string $detail = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?int $userId = null,
        ?int $clientId = null,
        array $metrics = [],
    ): string {
        $existing = AiInsight::query()
            ->where('organization_id', $organization->id)
            ->where('fingerprint', $fingerprint)
            ->first();

        $attributes = [
            'type' => $type,
            'severity' => $severity,
            'title' => Str::limit($title, 200, ''),
            'detail' => $detail,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'user_id' => $userId,
            'client_id' => $clientId,
            'metrics' => $metrics,
            'detected_at' => now(),
        ];

        if ($existing) {
            /*
             * Une alerte déjà vue par un agent reste « acknowledged » :
             * on met à jour les chiffres sans la faire remonter en tête.
             */
            $attributes['status'] = $existing->status === 'resolved'
                ? 'open'
                : $existing->status;

            $attributes['resolved_at'] = null;

            $existing->update($attributes);
        } else {
            AiInsight::create(array_merge($attributes, [
                'organization_id' => $organization->id,
                'fingerprint' => $fingerprint,
                'status' => 'open',
            ]));
        }

        return $fingerprint;
    }

    /**
     * Referme les alertes dont la cause a disparu.
     */
    private function closeResolved(Organization $organization, array $stillActive): void
    {
        AiInsight::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', ['open', 'acknowledged'])
            ->when(
                !empty($stillActive),
                fn ($query) => $query->whereNotIn('fingerprint', $stillActive)
            )
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);
    }

    private function humanizeMinutes(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' min';
        }

        $hours = intdiv($minutes, 60);

        if ($hours < 24) {
            return $hours . ' h';
        }

        return intdiv($hours, 24) . ' j';
    }

    private function humanizeHours(int $hours): string
    {
        return $hours < 24
            ? $hours . ' h'
            : intdiv($hours, 24) . ' j';
    }
}
