<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $organizationId = $user->organization_id;

        /*
        |--------------------------------------------------------------------------
        | Clients
        |--------------------------------------------------------------------------
        */

        $clientsCount = Client::query()
            ->where('organization_id', $organizationId)
            ->count();

        $todayClientsCount = Client::query()
            ->where('organization_id', $organizationId)
            ->whereDate('created_at', today())
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Conversations
        |--------------------------------------------------------------------------
        */

        $conversationsQuery = Conversation::query()
            ->where('organization_id', $organizationId);

        $conversationsCount = (clone $conversationsQuery)->count();

        /*
        |--------------------------------------------------------------------------
        | Répartition réelle des conversations par statut
        |--------------------------------------------------------------------------
        */

        $conversationStatusCounts = (clone $conversationsQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($value) => (int) $value);

        $openConversationsCount = (int) (
            $conversationStatusCounts['open'] ?? 0
        );

        $pendingConversationsCount = (int) (
            $conversationStatusCounts['pending'] ?? 0
        );

        $resolvedConversationsCount = (int) (
            $conversationStatusCounts['resolved'] ?? 0
        );

        $closedConversationsCount = (int) (
            $conversationStatusCounts['closed'] ?? 0
        );

        $aiEnabledConversationsCount = (clone $conversationsQuery)
            ->where('ai_enabled', true)
            ->count();

        $assignedConversationsCount = (clone $conversationsQuery)
            ->whereNotNull('assigned_to')
            ->count();

        $todayConversationsCount = (clone $conversationsQuery)
            ->whereDate('created_at', today())
            ->count();

        $todayResolvedConversationsCount = (clone $conversationsQuery)
            ->where('status', 'resolved')
            ->whereDate('updated_at', today())
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Réponses générées par l'IA
        |--------------------------------------------------------------------------
        */

        $aiMessagesQuery = Message::query()
            ->whereHas('conversation', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            })
            ->where('sender_type', 'ai')
            ->where('ai_generated', true);

        $aiMessagesCount = (clone $aiMessagesQuery)->count();

        $todayAiMessagesCount = (clone $aiMessagesQuery)
            ->whereDate('created_at', today())
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Agents actifs
        |--------------------------------------------------------------------------
        */

        $agentsCount = $user->organization
            ? $user->organization
                ->users()
                ->whereIn('role', ['agent', 'owner'])
                ->where('is_active', true)
                ->count()
            : 0;

        /*
        |--------------------------------------------------------------------------
        | Appels
        |--------------------------------------------------------------------------
        */

        $callsQuery = Call::query()
            ->where('organization_id', $organizationId);

        $callsCount = (clone $callsQuery)->count();

        $incomingCallsCount = (clone $callsQuery)
            ->where('type', 'incoming')
            ->count();

        $outgoingCallsCount = (clone $callsQuery)
            ->where('type', 'outgoing')
            ->count();

        $missedCallsCount = (clone $callsQuery)
            ->where('status', 'missed')
            ->count();

        $todayCallsCount = (clone $callsQuery)
            ->whereDate('started_at', today())
            ->count();

        $todayIncomingCallsCount = (clone $callsQuery)
            ->where('type', 'incoming')
            ->whereDate('started_at', today())
            ->count();

        $todayOutgoingCallsCount = (clone $callsQuery)
            ->where('type', 'outgoing')
            ->whereDate('started_at', today())
            ->count();

        $todayMissedCallsCount = (clone $callsQuery)
            ->where('status', 'missed')
            ->whereDate('started_at', today())
            ->count();

        $totalCallDuration = (clone $callsQuery)
            ->where('status', 'answered')
            ->sum('duration');

        $todayCallDuration = (clone $callsQuery)
            ->where('status', 'answered')
            ->whereDate('started_at', today())
            ->sum('duration');

        /*
        |--------------------------------------------------------------------------
        | Tickets
        |--------------------------------------------------------------------------
        */

        $ticketsQuery = Ticket::query()
            ->where('organization_id', $organizationId);

        $ticketsCount = (clone $ticketsQuery)->count();

        $openTicketsCount = (clone $ticketsQuery)
            ->whereIn('status', ['open', 'pending', 'in_progress'])
            ->count();

        $urgentTicketsCount = (clone $ticketsQuery)
            ->whereIn('status', ['open', 'pending', 'in_progress'])
            ->where('priority', 'urgent')
            ->count();

        $resolvedTicketsCount = (clone $ticketsQuery)
            ->whereIn('status', ['resolved', 'closed'])
            ->count();

        $todayTicketsCount = (clone $ticketsQuery)
            ->whereDate('created_at', today())
            ->count();

        $overdueTicketsCount = (clone $ticketsQuery)
            ->whereIn('status', ['open', 'pending', 'in_progress'])
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Séries sur quatorze jours
        |--------------------------------------------------------------------------
        */

        $series = [
            'days' => $this->dayLabels(),

            'conversations' => $this->dailySeries(
                Conversation::query()
                    ->where('organization_id', $organizationId),
                'created_at'
            ),

            'calls' => $this->dailySeries(
                Call::query()
                    ->where('organization_id', $organizationId),
                'started_at'
            ),

            'tickets' => $this->dailySeries(
                Ticket::query()
                    ->where('organization_id', $organizationId),
                'created_at'
            ),

            'ai_responses' => $this->dailySeries(
                Message::query()
                    ->whereHas(
                        'conversation',
                        fn ($query) => $query->where(
                            'organization_id',
                            $organizationId
                        )
                    )
                    ->where('sender_type', 'ai'),
                'created_at'
            ),
        ];

        /*
        |--------------------------------------------------------------------------
        | Conversations récentes
        |--------------------------------------------------------------------------
        */

        $recentConversations = Conversation::query()
            ->with(['client'])
            ->where('organization_id', $organizationId)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(function ($conversation) {
                return [
                    'id' => $conversation->id,
                    'subject' => $conversation->subject,
                    'channel' => $conversation->channel,
                    'status' => $conversation->status,
                    'priority' => $conversation->priority,

                    'ai_enabled' => (bool) $conversation->ai_enabled,

                    'last_message_at' => $conversation->last_message_at
                        ? $conversation->last_message_at->format('d/m/Y H:i')
                        : null,

                    'activity_at' => $conversation->last_message_at
                        ? $conversation->last_message_at->timestamp
                        : 0,

                    'client' => [
                        'id' => $conversation->client?->id,
                        'name' => $conversation->client?->full_name,
                        'email' => $conversation->client?->email,
                    ],
                ];
            });

        /*
        |--------------------------------------------------------------------------
        | Appels récents
        |--------------------------------------------------------------------------
        */

        $recentCalls = Call::query()
            ->with([
                'client:id,first_name,last_name,phone',
                'user:id,name',
            ])
            ->where('organization_id', $organizationId)
            ->when(
        $user->role === 'agent',
        fn ($query) => $query->where('user_id', $user->id)
    )
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(function ($call) {
                return [
                    'id' => $call->id,
                    'type' => $call->type,
                    'status' => $call->status,
                    'phone' => $call->phone,
                    'duration' => $call->duration,

                    'started_at' => $call->started_at
                        ? $call->started_at->format('d/m/Y H:i')
                        : null,

                    'activity_at' => $call->started_at
                        ? $call->started_at->timestamp
                        : 0,

                    'reason' => $call->reason,

                    'client' => [
                        'id' => $call->client?->id,
                        'name' => $call->client?->full_name,
                        'phone' => $call->client?->phone,
                    ],

                    'user' => [
                        'id' => $call->user?->id,
                        'name' => $call->user?->name,
                    ],
                ];
            });

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        return Inertia::render('Dashboard', [
            'statistics' => [

                // Clients
                'clients' => $clientsCount,
                'today_clients' => $todayClientsCount,

                // Conversations
                'conversations' => $conversationsCount,
                'today_conversations' => $todayConversationsCount,

                'open_conversations' => $openConversationsCount,
                'pending_conversations' => $pendingConversationsCount,
                'resolved_conversations' => $resolvedConversationsCount,
                'closed_conversations' => $closedConversationsCount,

                'today_resolved_conversations' =>
                    $todayResolvedConversationsCount,

                'ai_enabled_conversations' =>
                    $aiEnabledConversationsCount,

                'assigned_conversations' =>
                    $assignedConversationsCount,

                // IA
                'ai_responses' => $aiMessagesCount,
                'today_ai_responses' => $todayAiMessagesCount,

                // Agents
                'agents' => $agentsCount,

                // Appels
                'calls' => $callsCount,
                'incoming_calls' => $incomingCallsCount,
                'outgoing_calls' => $outgoingCallsCount,
                'missed_calls' => $missedCallsCount,

                'today_calls' => $todayCallsCount,
                'today_incoming_calls' => $todayIncomingCallsCount,
                'today_outgoing_calls' => $todayOutgoingCallsCount,
                'today_missed_calls' => $todayMissedCallsCount,

                'total_call_duration' => $totalCallDuration,
                'today_call_duration' => $todayCallDuration,

                // Tickets
                'tickets' => $ticketsCount,
                'open_tickets' => $openTicketsCount,
                'urgent_tickets' => $urgentTicketsCount,
                'resolved_tickets' => $resolvedTicketsCount,
                'today_tickets' => $todayTicketsCount,
                'overdue_tickets' => $overdueTicketsCount,
            ],

            'series' => $series,

            'recentConversations' => $recentConversations,
            'recentCalls' => $recentCalls,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Labels des jours
    |--------------------------------------------------------------------------
    */

    private function dayLabels(int $days = 14): array
    {
        $labels = [];

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $labels[] = now()
                ->subDays($offset)
                ->format('d/m');
        }

        return $labels;
    }

    /*
    |--------------------------------------------------------------------------
    | Séries quotidiennes
    |--------------------------------------------------------------------------
    */

    private function dailySeries(
        $query,
        string $column,
        int $days = 14
    ): array {
        $start = now()
            ->subDays($days - 1)
            ->startOfDay();

        $counts = $query
            ->where($column, '>=', $start)
            ->get([$column])
            ->groupBy(
                fn ($row) =>
                    optional($row->{$column})
                        ->format('Y-m-d')
            )
            ->map(
                fn ($group) =>
                    $group->count()
            );

        $series = [];

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $key = now()
                ->subDays($offset)
                ->format('Y-m-d');

            $series[] = (int) (
                $counts[$key] ?? 0
            );
        }

        return $series;
    }
}
