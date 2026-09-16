<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    /**
     * Vérifie que le client appartient bien
     * à l'organisation de l'utilisateur connecté.
     */
    private function authorizeClient(
        Request $request,
        Client $client
    ): void {
        abort_unless(
            $client->organization_id ===
                $request->user()->organization_id,
            403
        );
    }

    /**
     * Liste des clients.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $clients = Client::query()
            ->where(
                'organization_id',
                $user->organization_id
            )

            ->when(
                $request->filled('search'),
                function ($query) use ($request) {
                    $search = trim($request->input('search'));

                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('company', 'like', "%{$search}%");
                    });
                }
            )

            ->withCount('conversations')

            ->withCount([
                'conversations as open_conversations_count' => function ($query) {
                    $query->where('status', 'open');
                },
            ])

            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | Statistiques clients
        |--------------------------------------------------------------------------
        */

        $clientsQuery = Client::query()
            ->where(
                'organization_id',
                $user->organization_id
            );

        $totalClients = (clone $clientsQuery)->count();

        $todayClients = (clone $clientsQuery)
            ->whereDate('created_at', today())
            ->count();

        $clientsWithConversations = (clone $clientsQuery)
            ->has('conversations')
            ->count();

        $clientsWithoutConversations = $totalClients -
            $clientsWithConversations;

        return Inertia::render('Clients/Index', [
            'clients' => $clients,

            'statistics' => [
                'total' => $totalClients,
                'today' => $todayClients,
                'with_conversations' => $clientsWithConversations,
                'without_conversations' => max(
                    0,
                    $clientsWithoutConversations
                ),
            ],

            'filters' => [
                'search' => $request->input('search'),
            ],
        ]);
    }

    /**
     * Formulaire de création.
     */
    public function create(): Response
    {
        return Inertia::render('Clients/Create');
    }

    /**
     * Enregistrer un client.
     */
    public function store(
        StoreClientRequest $request
    ): RedirectResponse {
        $user = $request->user();

        $user->organization
            ->clients()
            ->create($request->validated());

        return redirect()
            ->route('clients.index')
            ->with(
                'success',
                'Client créé avec succès.'
            );
    }

    /**
     * Afficher la fiche client.
     */
    public function show(
        Request $request,
        Client $client
    ): Response {
        $this->authorizeClient(
            $request,
            $client
        );

        $client->loadCount('conversations');

        $client->load([
    'conversations' => function ($query) {
        $query
            ->with([
                'assignedAgent:id,name,role',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(20);
    },
]);
        return Inertia::render('Clients/Show', [
            'client' => $client,
        ]);
    }

    /**
     * Formulaire de modification.
     */
    public function edit(
        Request $request,
        Client $client
    ): Response {
        $this->authorizeClient(
            $request,
            $client
        );

        return Inertia::render('Clients/Edit', [
            'client' => $client,
        ]);
    }

    /**
     * Modifier un client.
     */
    public function update(
        UpdateClientRequest $request,
        Client $client
    ): RedirectResponse {
        $this->authorizeClient(
            $request,
            $client
        );

        $client->update(
            $request->validated()
        );

        return redirect()
            ->route('clients.index')
            ->with(
                'success',
                'Client modifié avec succès.'
            );
    }

    /**
     * Supprimer un client.
     */
    public function destroy(
        Request $request,
        Client $client
    ): RedirectResponse {
        $this->authorizeClient(
            $request,
            $client
        );

        /*
        |--------------------------------------------------------------------------
        | Protection contre la suppression d'un client
        | ayant des conversations
        |--------------------------------------------------------------------------
        */

        if ($client->conversations()->exists()) {
            return back()->with(
                'error',
                'Impossible de supprimer ce client car il possède des conversations.'
            );
        }

        $client->delete();

        return redirect()
            ->route('clients.index')
            ->with(
                'success',
                'Client supprimé avec succès.'
            );
    }
}
