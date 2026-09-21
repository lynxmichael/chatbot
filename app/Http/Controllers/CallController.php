<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Notifications\NewIncomingCall;
use Inertia\Response;

class CallController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $calls = Call::query()
            ->visibleTo($user)
            ->where('organization_id', $user->organization_id)
            ->with([
                'client:id,first_name,last_name,email,phone',
                'user:id,name',
                'conversation:id,subject',
            ])
            ->when(
                $user->role === 'agent',
                fn ($query) => $query->where('user_id', $user->id)
            )
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('phone', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhereHas('client', function ($clientQuery) use ($search) {
                            $clientQuery
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            })
            ->when(
                $request->type,
                fn ($query, $type) => $query->where('type', $type)
            )
            ->when(
                $request->status,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->latest('started_at')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Calls/Index', [
            'calls' => $calls,
            'filters' => [
                'search' => $request->search,
                'type' => $request->type,
                'status' => $request->status,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $organizationId = $user->organization_id;

        $clients = Client::query()
            ->where('organization_id', $organizationId)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
            ]);

        $conversations = Conversation::query()
            ->where('organization_id', $organizationId)
            ->with('client:id,first_name,last_name')
            ->latest('last_message_at')
            ->get([
                'id',
                'client_id',
                'subject',
                'status',
            ]);

        $agents = User::query()
            ->where('organization_id', $organizationId)
            ->whereIn('role', ['owner', 'agent'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'role',
            ]);

        return Inertia::render('Calls/Create', [
            'clients' => $clients,
            'conversations' => $conversations,
            'agents' => $agents,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $organizationId = $user->organization_id;

        $validated = $request->validate([
            'client_id' => ['required', 'integer'],
            'conversation_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'type' => ['required', 'in:incoming,outgoing'],
            'status' => ['required', Rule::in(Call::MANUAL_STATUSES)],
            'phone' => ['required', 'string', 'max:30'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
        ]);

        $client = Client::query()
            ->where('organization_id', $organizationId)
            ->findOrFail($validated['client_id']);

        if (!empty($validated['conversation_id'])) {
            $conversation = Conversation::query()
                ->where('organization_id', $organizationId)
                ->findOrFail($validated['conversation_id']);

            if ($conversation->client_id !== $client->id) {
                return back()
                    ->withErrors([
                        'conversation_id' => 'La conversation sélectionnée n’appartient pas à ce client.',
                    ])
                    ->withInput();
            }
        }

        $assignedUserId = $user->id;

        if ($user->hasAbility('records.assign') && !empty($validated['user_id'])) {
            $assignedUser = User::query()
                ->where('organization_id', $organizationId)
                ->whereIn('role', ['owner', 'agent'])
                ->where('is_active', true)
                ->findOrFail($validated['user_id']);

            $assignedUserId = $assignedUser->id;
        }

        $call = Call::create([
            'organization_id' => $organizationId,
            'client_id' => $client->id,
            'conversation_id' => $validated['conversation_id'] ?? null,
            'user_id' => $assignedUserId,
            'type' => $validated['type'],
            'status' => $validated['status'],
            'phone' => $validated['phone'],
            'duration' => $validated['duration'] ?? 0,
            'reason' => $validated['reason'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'started_at' => $validated['started_at'] ?? null,
            'ended_at' => $validated['ended_at'] ?? null,
        ]);
        if ($call->type === 'incoming') {
            $call->loadMissing('client');

            $recipients = User::query()
                ->where('organization_id', $organizationId)
                ->where('is_active', true)
                ->whereIn('role', ['owner', 'agent'])
                ->get();

            foreach ($recipients as $recipient) {
                $recipient->notify(new NewIncomingCall($call));
            }
        }

        return redirect()
            ->route('calls.index')
            ->with('success', 'Appel enregistré avec succès.');
    }

    public function show(Request $request, Call $call): Response
    {
        $this->authorizeCall($request, $call);

        $call->load([
            'client',
            'conversation',
            'user:id,name,email,role',
        ]);

        return Inertia::render('Calls/Show', [
            'call' => $call,
        ]);
    }

    public function edit(Request $request, Call $call): Response
    {
        $this->authorizeCall($request, $call);

        $user = $request->user();
        $organizationId = $user->organization_id;

        $clients = Client::query()
            ->where('organization_id', $organizationId)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
            ]);

        $conversations = Conversation::query()
            ->where('organization_id', $organizationId)
            ->with('client:id,first_name,last_name')
            ->latest('last_message_at')
            ->get([
                'id',
                'client_id',
                'subject',
                'status',
            ]);

        $agents = User::query()
            ->where('organization_id', $organizationId)
            ->whereIn('role', ['owner', 'agent'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'role',
            ]);

        return Inertia::render('Calls/Edit', [
            'call' => $call->load(['client', 'conversation', 'user']),
            'clients' => $clients,
            'conversations' => $conversations,
            'agents' => $agents,
        ]);
    }

    public function update(Request $request, Call $call): RedirectResponse
    {
        $this->authorizeCall($request, $call);

        $user = $request->user();
        $organizationId = $user->organization_id;

        $validated = $request->validate([
            'client_id' => ['required', 'integer'],
            'conversation_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'type' => ['required', 'in:incoming,outgoing'],
            'status' => ['required', Rule::in(Call::MANUAL_STATUSES)],
            'phone' => ['required', 'string', 'max:30'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
        ]);

        $client = Client::query()
            ->where('organization_id', $organizationId)
            ->findOrFail($validated['client_id']);

        if (!empty($validated['conversation_id'])) {
            $conversation = Conversation::query()
                ->where('organization_id', $organizationId)
                ->findOrFail($validated['conversation_id']);

            if ($conversation->client_id !== $client->id) {
                return back()
                    ->withErrors([
                        'conversation_id' => 'La conversation sélectionnée n’appartient pas à ce client.',
                    ])
                    ->withInput();
            }
        }

        $assignedUserId = $call->user_id;

        if ($user->role === 'agent') {
            $assignedUserId = $user->id;
        } elseif (!empty($validated['user_id'])) {
            $assignedUser = User::query()
                ->where('organization_id', $organizationId)
                ->whereIn('role', ['owner', 'agent'])
                ->where('is_active', true)
                ->findOrFail($validated['user_id']);

            $assignedUserId = $assignedUser->id;
        }

        $call->update([
            'client_id' => $client->id,
            'conversation_id' => $validated['conversation_id'] ?? null,
            'user_id' => $assignedUserId,
            'type' => $validated['type'],
            'status' => $validated['status'],
            'phone' => $validated['phone'],
            'duration' => $validated['duration'] ?? 0,
            'reason' => $validated['reason'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'started_at' => $validated['started_at'] ?? null,
            'ended_at' => $validated['ended_at'] ?? null,
        ]);

        return redirect()
            ->route('calls.show', $call)
            ->with('success', 'Appel modifié avec succès.');
    }

    public function destroy(Request $request, Call $call): RedirectResponse
    {
        $this->authorizeCall($request, $call);

        $call->delete();

        return redirect()
            ->route('calls.index')
            ->with('success', 'Appel supprimé avec succès.');
    }

    /**
     * Même règle que les conversations et les tickets.
     *
     * La version précédente était plus stricte : elle refusait aussi
     * les appels sans agent, qu'un agent doit pourtant pouvoir
     * reprendre. Et elle ne tenait pas compte du mode « team ».
     */
    private function authorizeCall(Request $request, Call $call): void
    {
        abort_unless($call->isVisibleTo($request->user()), 403);
    }
}
