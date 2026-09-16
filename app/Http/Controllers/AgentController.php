<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class AgentController extends Controller
{
    /**
     * Vérifie que l'utilisateur connecté est le responsable.
     */
    private function authorizeOwner(Request $request): void
    {
        abort_unless(
            $request->user()->role === 'owner',
            403
        );
    }

    /**
     * Vérifie que l'agent appartient à l'organisation.
     */
    private function authorizeAgentOrganization(
        Request $request,
        User $agent
    ): void {
        abort_unless(
            $agent->organization_id ===
                $request->user()->organization_id,
            403
        );

        abort_unless(
            in_array($agent->role, ['agent', 'owner'], true),
            404
        );
    }

    /**
     * Liste des agents de l'organisation.
     */
    public function index(Request $request)
    {
        $this->authorizeOwner($request);

        $user = $request->user();

        $agents = User::query()
            ->where(
                'organization_id',
                $user->organization_id
            )
            ->whereIn('role', ['agent', 'owner'])
            ->withCount([
                'assignedConversations as conversations_count',
            ])
            ->orderByRaw(
                "CASE WHEN role = 'owner' THEN 0 ELSE 1 END"
            )
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Agents/Index', [
            'agents' => $agents,
        ]);
    }

    /**
     * Formulaire de création.
     */
    public function create(Request $request)
    {
        $this->authorizeOwner($request);

        return Inertia::render('Agents/Create');
    }

    /**
     * Enregistrer un nouvel agent.
     */
    public function store(Request $request)
    {
        $this->authorizeOwner($request);

        $user = $request->user();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make(
                $validated['password']
            ),
            'organization_id' => $user->organization_id,
            'role' => 'agent',
            'is_active' => true,
        ]);

        return redirect()
            ->route('agents.index')
            ->with(
                'success',
                'Agent créé avec succès.'
            );
    }

    /**
     * Formulaire de modification.
     */
    public function edit(
        Request $request,
        User $agent
    ) {
        $this->authorizeOwner($request);

        $this->authorizeAgentOrganization(
            $request,
            $agent
        );

        return Inertia::render('Agents/Edit', [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'role' => $agent->role,
                'is_active' => (bool) $agent->is_active,
            ],
        ]);
    }

    /**
     * Mettre à jour un agent.
     */
    public function update(
        Request $request,
        User $agent
    ) {
        $this->authorizeOwner($request);

        $this->authorizeAgentOrganization(
            $request,
            $agent
        );

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email,' . $agent->id,
            ],

            'password' => [
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        $agent->name = $validated['name'];
        $agent->email = $validated['email'];

        if (!empty($validated['password'])) {
            $agent->password = Hash::make(
                $validated['password']
            );
        }

        $agent->save();

        return redirect()
            ->route('agents.index')
            ->with(
                'success',
                'Agent mis à jour avec succès.'
            );
    }

    /**
     * Activer ou désactiver un agent.
     */
    public function toggle(
        Request $request,
        User $agent
    ) {
        $this->authorizeOwner($request);

        $this->authorizeAgentOrganization(
            $request,
            $agent
        );

        abort_unless(
            $agent->role === 'agent',
            403
        );

        $agent->update([
            'is_active' => !$agent->is_active,
        ]);

        return back()->with(
            'success',
            $agent->is_active
                ? 'Agent activé avec succès.'
                : 'Agent désactivé avec succès.'
        );
    }

    /**
     * Supprimer un agent.
     */
    public function destroy(
        Request $request,
        User $agent
    ) {
        $this->authorizeOwner($request);

        $this->authorizeAgentOrganization(
            $request,
            $agent
        );

        abort_unless(
            $agent->role === 'agent',
            403
        );

        $hasConversations = Conversation::query()
            ->where(
                'organization_id',
                $request->user()->organization_id
            )
            ->where(
                'assigned_to',
                $agent->id
            )
            ->exists();

        if ($hasConversations) {
            return back()->with(
                'error',
                'Impossible de supprimer cet agent car des conversations lui sont attribuées.'
            );
        }

        $agent->delete();

        return redirect()
            ->route('agents.index')
            ->with(
                'success',
                'Agent supprimé avec succès.'
            );
    }
}
