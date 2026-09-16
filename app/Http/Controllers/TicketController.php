<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Conversation;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    /**
     * Vérifie que l'utilisateur est authentifié
     * et que le ticket appartient à son organisation.
     */
    private function authorizeTicket(Request $request, Ticket $ticket): void
    {
        $user = $request->user();

        abort_unless(
            $user &&
            $ticket->organization_id === $user->organization_id,
            403
        );
    }

    /**
     * Vérifie que l'utilisateur est authentifié.
     */
    private function authorizeUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user, 403);

        return $user;
    }

    /**
     * Vérifie qu'une action nécessite au minimum
     * un owner ou un agent actif.
     */
    private function authorizeTicketManagement(Request $request): User
    {
        $user = $this->authorizeUser($request);

        abort_unless(
            in_array($user->role, ['owner', 'agent'], true) &&
            $user->is_active,
            403
        );

        return $user;
    }

    /**
     * Vérifie que l'utilisateur est owner.
     */
    private function authorizeOwner(Request $request): User
    {
        $user = $this->authorizeUser($request);

        abort_unless(
            $user->role === 'owner' &&
            $user->is_active,
            403
        );

        return $user;
    }

    /**
     * Liste des tickets.
     */
    public function index(Request $request): Response
    {
        $user = $this->authorizeUser($request);

        $query = Ticket::query()
            ->where('organization_id', $user->organization_id)
            ->with([
                'client:id,first_name,last_name,email,phone',
                'assignedAgent:id,name,email,role',
            ])
            ->latest();

        /*
        |--------------------------------------------------------------------------
        | Recherche
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $search = trim(
                $request->string('search')->toString()
            );

            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Filtres
        |--------------------------------------------------------------------------
        */

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')->toString()
            );
        }

        if ($request->filled('priority')) {
            $query->where(
                'priority',
                $request->string('priority')->toString()
            );
        }

        if ($request->filled('category')) {
            $query->where(
                'category',
                $request->string('category')->toString()
            );
        }

        if ($request->filled('assigned_to')) {
            $query->where(
                'assigned_to',
                $request->integer('assigned_to')
            );
        }

        if ($request->filled('channel')) {
            $query->where(
                'channel',
                $request->string('channel')->toString()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        $tickets = $query
            ->paginate(15)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | Agents
        |--------------------------------------------------------------------------
        */

        $agents = User::query()
            ->where('organization_id', $user->organization_id)
            ->whereIn('role', ['owner', 'agent'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'role',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Clients
        |--------------------------------------------------------------------------
        */

        $clients = Client::query()
            ->where('organization_id', $user->organization_id)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
            ])
            ->map(function ($client) {
                return [
                    'id' => $client->id,
                    'first_name' => $client->first_name,
                    'last_name' => $client->last_name,
                    'full_name' => $client->full_name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                ];
            });

        /*
        |--------------------------------------------------------------------------
        | Statistiques
        |--------------------------------------------------------------------------
        */

        $baseStatsQuery = Ticket::query()
            ->where('organization_id', $user->organization_id);

        $statistics = [
            'total' => (clone $baseStatsQuery)->count(),

            'open' => (clone $baseStatsQuery)
                ->where('status', 'open')
                ->count(),

            'pending' => (clone $baseStatsQuery)
                ->where('status', 'pending')
                ->count(),

            'in_progress' => (clone $baseStatsQuery)
                ->where('status', 'in_progress')
                ->count(),

            'resolved' => (clone $baseStatsQuery)
                ->where('status', 'resolved')
                ->count(),

            'urgent' => (clone $baseStatsQuery)
                ->where('priority', 'urgent')
                ->whereNotIn('status', ['resolved', 'closed'])
                ->count(),

            'unassigned' => (clone $baseStatsQuery)
                ->whereNull('assigned_to')
                ->whereNotIn('status', ['resolved', 'closed'])
                ->count(),

            'today' => (clone $baseStatsQuery)
                ->whereDate(
                    'created_at',
                    now()->toDateString()
                )
                ->count(),
        ];

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,
            'statistics' => $statistics,
            'agents' => $agents,
            'clients' => $clients,

            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
                'priority' => $request->input('priority', ''),
                'category' => $request->input('category', ''),
                'assigned_to' => $request->input('assigned_to', ''),
                'channel' => $request->input('channel', ''),
            ],
        ]);
    }

    /**
     * Formulaire de création.
     */
    public function create(Request $request): Response
    {
        $user = $this->authorizeTicketManagement($request);

        /*
        |--------------------------------------------------------------------------
        | Clients
        |--------------------------------------------------------------------------
        */

        $clients = Client::query()
            ->where('organization_id', $user->organization_id)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
            ])
            ->map(function ($client) {
                return [
                    'id' => $client->id,
                    'first_name' => $client->first_name,
                    'last_name' => $client->last_name,
                    'full_name' => $client->full_name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                ];
            });

        /*
        |--------------------------------------------------------------------------
        | Conversations
        |--------------------------------------------------------------------------
        */

        $conversations = Conversation::query()
            ->where('organization_id', $user->organization_id)
            ->latest()
            ->get([
                'id',
                'client_id',
                'subject',
                'channel',
                'status',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Agents
        |--------------------------------------------------------------------------
        */

        $agents = User::query()
            ->where('organization_id', $user->organization_id)
            ->whereIn('role', ['owner', 'agent'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'role',
            ]);

        return Inertia::render('Tickets/Create', [
            'clients' => $clients,
            'conversations' => $conversations,
            'agents' => $agents,
        ]);
    }

    /**
     * Enregistre un nouveau ticket.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $this->authorizeTicketManagement($request);

        $validated = $request->validate([
            'client_id' => [
                'required',
                'integer',
                'exists:clients,id',
            ],

            'conversation_id' => [
                'nullable',
                'integer',
                'exists:conversations,id',
            ],

            'assigned_to' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],

            'subject' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'required',
                'string',
            ],

            'category' => [
                'required',
                'string',
                'max:100',
            ],

            'status' => [
                'required',
                'in:open,pending,in_progress,resolved,closed',
            ],

            'priority' => [
                'required',
                'in:low,normal,high,urgent',
            ],

            'channel' => [
                'required',
                'in:web,widget,whatsapp,email,phone',
            ],

            'sla_due_at' => [
                'nullable',
                'date',
            ],

            'resolution' => [
                'nullable',
                'string',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Vérification client
        |--------------------------------------------------------------------------
        */

        $clientExists = Client::query()
            ->where('organization_id', $user->organization_id)
            ->whereKey($validated['client_id'])
            ->exists();

        abort_unless($clientExists, 403);

        /*
        |--------------------------------------------------------------------------
        | Vérification conversation
        |--------------------------------------------------------------------------
        */

        if (!empty($validated['conversation_id'])) {
            $conversation = Conversation::query()
                ->where('organization_id', $user->organization_id)
                ->whereKey($validated['conversation_id'])
                ->first();

            abort_unless($conversation, 403);

            /*
            |--------------------------------------------------------------------------
            | Vérification client ↔ conversation
            |--------------------------------------------------------------------------
            */

            abort_unless(
                (int) $conversation->client_id ===
                (int) $validated['client_id'],
                422
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Vérification agent
        |--------------------------------------------------------------------------
        */

        if (!empty($validated['assigned_to'])) {
            $agentExists = User::query()
                ->where('organization_id', $user->organization_id)
                ->whereIn('role', ['owner', 'agent'])
                ->where('is_active', true)
                ->whereKey($validated['assigned_to'])
                ->exists();

            abort_unless($agentExists, 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Numéro unique
        |--------------------------------------------------------------------------
        */

        do {
            $ticketNumber = 'TCK-' . strtoupper(
                Str::random(8)
            );
        } while (
            Ticket::query()
                ->where('ticket_number', $ticketNumber)
                ->exists()
        );

        /*
        |--------------------------------------------------------------------------
        | Dates automatiques
        |--------------------------------------------------------------------------
        */

        $firstResponseAt = null;
        $resolvedAt = null;
        $closedAt = null;

        if ($validated['status'] !== 'open') {
            $firstResponseAt = now();
        }

        if ($validated['status'] === 'resolved') {
            $resolvedAt = now();
        }

        if ($validated['status'] === 'closed') {
            $resolvedAt = now();
            $closedAt = now();
        }

        /*
        |--------------------------------------------------------------------------
        | Création
        |--------------------------------------------------------------------------
        */

        Ticket::create([
            'organization_id' => $user->organization_id,

            'client_id' => $validated['client_id'],

            'conversation_id' =>
                $validated['conversation_id'] ?? null,

            'assigned_to' =>
                $validated['assigned_to'] ?? null,

            'ticket_number' => $ticketNumber,

            'subject' => $validated['subject'],

            'description' => $validated['description'],

            'category' => $validated['category'],

            'status' => $validated['status'],

            'priority' => $validated['priority'],

            'channel' => $validated['channel'],

            'sla_due_at' =>
                $validated['sla_due_at'] ?? null,

            'first_response_at' => $firstResponseAt,

            'resolution' =>
                $validated['resolution'] ?? null,

            'resolved_at' => $resolvedAt,

            'closed_at' => $closedAt,
        ]);

        return redirect()
            ->route('tickets.index')
            ->with(
                'success',
                'Ticket créé avec succès.'
            );
    }

    /**
     * Affiche un ticket.
     */
    public function show(
        Request $request,
        Ticket $ticket
    ): Response {
        $this->authorizeTicket($request, $ticket);

        $ticket->load([
            'client:id,organization_id,first_name,last_name,email,phone,company',
            'conversation',
            'assignedAgent:id,name,email,role',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Agents disponibles
        |--------------------------------------------------------------------------
        */

        $agents = User::query()
            ->where(
                'organization_id',
                $request->user()->organization_id
            )
            ->whereIn('role', ['owner', 'agent'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'role',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Client pour le frontend
        |--------------------------------------------------------------------------
        */

        $client = $ticket->client;

        $clientData = null;

        if ($client) {
            $clientData = [
                'id' => $client->id,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'full_name' => $client->full_name,
                'email' => $client->email,
                'phone' => $client->phone,
                'company' => $client->company,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Ticket pour le frontend
        |--------------------------------------------------------------------------
        */

        $ticketData = $ticket->toArray();

        $ticketData['client'] = $clientData;

        return Inertia::render('Tickets/Show', [
            'ticket' => $ticketData,
            'agents' => $agents,
        ]);
    }

    /**
     * Formulaire de modification.
     */
    public function edit(
        Request $request,
        Ticket $ticket
    ): Response {
        $this->authorizeTicket($request, $ticket);

        $user = $this->authorizeTicketManagement($request);

        $ticket->load([
            'client:id,organization_id,first_name,last_name,email,phone,company',
            'conversation',
            'assignedAgent:id,name,email,role',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Clients
        |--------------------------------------------------------------------------
        */

        $clients = Client::query()
            ->where(
                'organization_id',
                $user->organization_id
            )
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
            ])
            ->map(function ($client) {
                return [
                    'id' => $client->id,
                    'first_name' => $client->first_name,
                    'last_name' => $client->last_name,
                    'full_name' => $client->full_name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                ];
            });

        /*
        |--------------------------------------------------------------------------
        | Conversations
        |--------------------------------------------------------------------------
        */

        $conversations = Conversation::query()
            ->where(
                'organization_id',
                $user->organization_id
            )
            ->latest()
            ->get([
                'id',
                'client_id',
                'subject',
                'channel',
                'status',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Agents
        |--------------------------------------------------------------------------
        */

        $agents = User::query()
            ->where(
                'organization_id',
                $user->organization_id
            )
            ->whereIn('role', ['owner', 'agent'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'role',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Ticket client
        |--------------------------------------------------------------------------
        */

        $ticketData = $ticket->toArray();

        if ($ticket->client) {
            $ticketData['client'] = [
                'id' => $ticket->client->id,
                'first_name' => $ticket->client->first_name,
                'last_name' => $ticket->client->last_name,
                'full_name' => $ticket->client->full_name,
                'email' => $ticket->client->email,
                'phone' => $ticket->client->phone,
                'company' => $ticket->client->company,
            ];
        }

        return Inertia::render('Tickets/Edit', [
            'ticket' => $ticketData,
            'clients' => $clients,
            'conversations' => $conversations,
            'agents' => $agents,
        ]);
    }

    /**
     * Met à jour un ticket.
     */
    public function update(
        Request $request,
        Ticket $ticket
    ): RedirectResponse {
        $this->authorizeTicket($request, $ticket);

        $user = $this->authorizeTicketManagement($request);

        $validated = $request->validate([
            'client_id' => [
                'required',
                'integer',
                'exists:clients,id',
            ],

            'conversation_id' => [
                'nullable',
                'integer',
                'exists:conversations,id',
            ],

            'assigned_to' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],

            'subject' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'required',
                'string',
            ],

            'category' => [
                'required',
                'string',
                'max:100',
            ],

            'status' => [
                'required',
                'in:open,pending,in_progress,resolved,closed',
            ],

            'priority' => [
                'required',
                'in:low,normal,high,urgent',
            ],

            'channel' => [
                'required',
                'in:web,widget,whatsapp,email,phone',
            ],

            'sla_due_at' => [
                'nullable',
                'date',
            ],

            'resolution' => [
                'nullable',
                'string',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Vérification client
        |--------------------------------------------------------------------------
        */

        $clientExists = Client::query()
            ->where(
                'organization_id',
                $user->organization_id
            )
            ->whereKey($validated['client_id'])
            ->exists();

        abort_unless($clientExists, 403);

        /*
        |--------------------------------------------------------------------------
        | Vérification conversation
        |--------------------------------------------------------------------------
        */

        if (!empty($validated['conversation_id'])) {
            $conversation = Conversation::query()
                ->where(
                    'organization_id',
                    $user->organization_id
                )
                ->whereKey($validated['conversation_id'])
                ->first();

            abort_unless($conversation, 403);

            /*
            |--------------------------------------------------------------------------
            | Vérification client ↔ conversation
            |--------------------------------------------------------------------------
            */

            abort_unless(
                (int) $conversation->client_id ===
                (int) $validated['client_id'],
                422
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Vérification agent
        |--------------------------------------------------------------------------
        */

        if (!empty($validated['assigned_to'])) {
            $agentExists = User::query()
                ->where(
                    'organization_id',
                    $user->organization_id
                )
                ->whereIn('role', ['owner', 'agent'])
                ->where('is_active', true)
                ->whereKey($validated['assigned_to'])
                ->exists();

            abort_unless($agentExists, 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Gestion des dates
        |--------------------------------------------------------------------------
        */

        $oldStatus = $ticket->status;
        $newStatus = $validated['status'];

        $firstResponseAt =
            $ticket->first_response_at;

        $resolvedAt =
            $ticket->resolved_at;

        $closedAt =
            $ticket->closed_at;

        /*
        |--------------------------------------------------------------------------
        | Première réponse
        |--------------------------------------------------------------------------
        */

        if (
            $oldStatus === 'open' &&
            $newStatus !== 'open' &&
            !$firstResponseAt
        ) {
            $firstResponseAt = now();
        }

        /*
        |--------------------------------------------------------------------------
        | Résolu
        |--------------------------------------------------------------------------
        */

        if ($newStatus === 'resolved') {
            if (!$resolvedAt) {
                $resolvedAt = now();
            }

            $closedAt = null;
        }

        /*
        |--------------------------------------------------------------------------
        | Fermé
        |--------------------------------------------------------------------------
        */

        elseif ($newStatus === 'closed') {
            if (!$resolvedAt) {
                $resolvedAt = now();
            }

            if (!$closedAt) {
                $closedAt = now();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Retour à un état actif
        |--------------------------------------------------------------------------
        */

        else {
            $resolvedAt = null;
            $closedAt = null;
        }

        /*
        |--------------------------------------------------------------------------
        | Mise à jour
        |--------------------------------------------------------------------------
        */

        $ticket->update([
            'client_id' =>
                $validated['client_id'],

            'conversation_id' =>
                $validated['conversation_id'] ?? null,

            'assigned_to' =>
                $validated['assigned_to'] ?? null,

            'subject' =>
                $validated['subject'],

            'description' =>
                $validated['description'],

            'category' =>
                $validated['category'],

            'status' =>
                $validated['status'],

            'priority' =>
                $validated['priority'],

            'channel' =>
                $validated['channel'],

            'sla_due_at' =>
                $validated['sla_due_at'] ?? null,

            'first_response_at' =>
                $firstResponseAt,

            'resolution' =>
                $validated['resolution'] ?? null,

            'resolved_at' =>
                $resolvedAt,

            'closed_at' =>
                $closedAt,
        ]);

        return redirect()
            ->route(
                'tickets.show',
                $ticket
            )
            ->with(
                'success',
                'Ticket mis à jour avec succès.'
            );
    }

    /**
     * Supprime un ticket.
     *
     * Suppression réservée au owner.
     */
    public function destroy(
        Request $request,
        Ticket $ticket
    ): RedirectResponse {
        $this->authorizeTicket($request, $ticket);

        $this->authorizeOwner($request);

        $ticket->delete();

        return redirect()
            ->route('tickets.index')
            ->with(
                'success',
                'Ticket supprimé avec succès.'
            );
    }
}
