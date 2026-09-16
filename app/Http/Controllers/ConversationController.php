<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConversationController extends Controller
{
    /**
     * Vérifie que la conversation appartient bien
     * à l'organisation de l'utilisateur connecté.
     */
    private function authorizeOrganization(
        Request $request,
        Conversation $conversation
    ): void {
        abort_unless(
            $conversation->organization_id === $request->user()->organization_id,
            403
        );
    }

    /**
     * Vérifie que l'utilisateur peut modifier la conversation.
     *
     * - owner : peut modifier toutes les conversations de son organisation
     * - agent : peut modifier uniquement les conversations qui lui sont attribuées
     */
    private function authorizeModification(
        Request $request,
        Conversation $conversation
    ): void {
        $user = $request->user();

        $this->authorizeOrganization(
            $request,
            $conversation
        );

        /*
         * Le propriétaire peut tout modifier.
         */
        if ($user->role === 'owner') {
            return;
        }

        /*
         * Un agent ne peut modifier que ses conversations.
         */
        abort_unless(
            $user->role === 'agent'
            && $conversation->assigned_to === $user->id,
            403
        );
    }

    /**
     * Liste des conversations.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        /*
         * Charger le client et l'agent affecté.
         */
        $query = Conversation::with([
            'client',
            'assignedAgent',
        ])->where(
            'organization_id',
            $user->organization_id
        );

        /*
        |--------------------------------------------------------------------------
        | Recherche
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where(
                    'subject',
                    'like',
                    "%{$search}%"
                )
                ->orWhereHas(
                    'client',
                    function ($clientQuery) use ($search) {
                        $clientQuery
                            ->where(
                                'first_name',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'last_name',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'email',
                                'like',
                                "%{$search}%"
                            );
                    }
                );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Filtre statut
        |--------------------------------------------------------------------------
        */

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->status
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Filtre priorité
        |--------------------------------------------------------------------------
        */

        if ($request->filled('priority')) {
            $query->where(
                'priority',
                $request->priority
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Filtre IA
        |--------------------------------------------------------------------------
        */

        if (
            $request->has('ai_enabled')
            && $request->ai_enabled !== ''
        ) {
            $query->where(
                'ai_enabled',
                filter_var(
                    $request->ai_enabled,
                    FILTER_VALIDATE_BOOLEAN
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Filtre agent
        |--------------------------------------------------------------------------
        */

        if ($request->filled('assigned_to')) {
            $query->where(
                'assigned_to',
                $request->integer('assigned_to')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Agent connecté
        |--------------------------------------------------------------------------
        |
        | Un agent ne voit que les conversations qui lui sont attribuées.
        |
        */

        if ($user->role === 'agent') {
            $query->where(
                'assigned_to',
                $user->id
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        $conversations = $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | Liste des agents disponibles
        |--------------------------------------------------------------------------
        */

        $agents = User::where(
            'organization_id',
            $user->organization_id
        )
            ->whereIn(
                'role',
                ['agent', 'owner']
            )
            ->where(
                'is_active',
                true
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'role',
            ]);

        return Inertia::render(
            'Conversations/Index',
            [
                'conversations' => $conversations,

                'agents' => $agents,

                'filters' => [
                    'search' => $request->search,
                    'status' => $request->status,
                    'priority' => $request->priority,
                    'ai_enabled' => $request->ai_enabled,
                    'assigned_to' => $request->assigned_to,
                ],
            ]
        );
    }

    /**
     * Affiche une conversation.
     */
    public function show(
        Request $request,
        Conversation $conversation
    ) {
        $user = $request->user();

        $this->authorizeOrganization(
            $request,
            $conversation
        );

        /*
        |--------------------------------------------------------------------------
        | Un agent ne peut voir que ses conversations
        |--------------------------------------------------------------------------
        */

        if (
            $user->role === 'agent'
            && $conversation->assigned_to !== $user->id
        ) {
            abort(403);
        }

        /*
        |--------------------------------------------------------------------------
        | Charger la conversation
        |--------------------------------------------------------------------------
        */

        $conversation->load([
            'client',

            'assignedAgent',

            'messages' => function ($query) {
                $query
                    ->with('user')
                    ->orderBy('created_at')
                    ->orderBy('id');
            },
        ]);

        /*
        |--------------------------------------------------------------------------
        | Agents disponibles de l'organisation
        |--------------------------------------------------------------------------
        */

        $agents = User::where(
            'organization_id',
            $user->organization_id
        )
            ->whereIn(
                'role',
                ['agent', 'owner']
            )
            ->where(
                'is_active',
                true
            )
            ->orderBy('role')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'role',
                'is_active',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Vérification des droits
        |--------------------------------------------------------------------------
        */

        $canModify = false;

        if ($user->role === 'owner') {
            $canModify = true;
        } elseif (
            $user->role === 'agent'
            && $conversation->assigned_to === $user->id
        ) {
            $canModify = true;
        }

        return Inertia::render(
            'Conversations/Show',
            [
                'conversation' => $conversation,
                'agents' => $agents,
                'canModify' => $canModify,
                'isOwner' => $user->role === 'owner',
                'currentUserId' => $user->id,
            ]
        );
    }

    /**
     * Crée un nouveau message dans une conversation.
     */
    public function store(
        Request $request,
        Conversation $conversation
    ) {
        $this->authorizeModification(
            $request,
            $conversation
        );

        $validated = $request->validate([
            'message' => [
                'required',
                'string',
                'max:10000',
            ],
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'sender_type' => 'agent',
            'content' => $validated['message'],
            'channel' => $conversation->channel,
            'ai_generated' => false,
            'ai_processed' => true,
            'ai_status' => 'completed',
            'ai_error' => null,
        ]);

        $conversation->update([
            'last_message_at' => now(),
        ]);

        return redirect()
            ->route(
                'conversations.show',
                $conversation
            )
            ->with(
                'success',
                'Message envoyé avec succès.'
            );
    }

    /**
     * Met à jour une conversation.
     */
    public function update(
        Request $request,
        Conversation $conversation
    ) {
        $this->authorizeModification(
            $request,
            $conversation
        );

        $user = $request->user();

        $validated = $request->validate([
            'status' => [
                'sometimes',
                'required',
                'in:open,pending,resolved,closed',
            ],

            'priority' => [
                'sometimes',
                'required',
                'in:low,normal,high,urgent',
            ],

            'ai_enabled' => [
                'sometimes',
                'boolean',
            ],

            'assigned_to' => [
                'sometimes',
                'nullable',
                'integer',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Attribution
        |--------------------------------------------------------------------------
        */

        if (
            array_key_exists(
                'assigned_to',
                $validated
            )
        ) {
            /*
             * Seul le owner peut changer l'attribution.
             */
            abort_unless(
                $user->role === 'owner',
                403
            );

            /*
             * Si une personne est sélectionnée,
             * elle doit appartenir à la même organisation
             * et être active.
             */
            if ($validated['assigned_to']) {
                $agentExists = User::where(
                    'id',
                    $validated['assigned_to']
                )
                    ->where(
                        'organization_id',
                        $user->organization_id
                    )
                    ->whereIn(
                        'role',
                        ['agent', 'owner']
                    )
                    ->where(
                        'is_active',
                        true
                    )
                    ->exists();

                abort_unless(
                    $agentExists,
                    422
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Mise à jour
        |--------------------------------------------------------------------------
        */

        $conversation->update(
            $validated
        );

        /*
        |--------------------------------------------------------------------------
        | Actualisation de last_message_at
        |--------------------------------------------------------------------------
        */

        if (
            array_key_exists(
                'status',
                $validated
            )
            || array_key_exists(
                'priority',
                $validated
            )
            || array_key_exists(
                'ai_enabled',
                $validated
            )
            || array_key_exists(
                'assigned_to',
                $validated
            )
        ) {
            $conversation->update([
                'last_message_at' =>
                    $conversation->last_message_at
                    ?? now(),
            ]);
        }

        return back()->with(
            'success',
            'Conversation mise à jour avec succès.'
        );
    }
}
