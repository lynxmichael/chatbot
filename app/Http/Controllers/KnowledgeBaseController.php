<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKnowledgeBaseRequest;
use App\Http\Requests\UpdateKnowledgeBaseRequest;
use App\Models\KnowledgeBase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeBaseController extends Controller
{
    /**
     * Vérifie que l'entrée appartient bien
     * à l'organisation de l'utilisateur connecté.
     */
    private function authorizeKnowledgeBase(
        Request $request,
        KnowledgeBase $knowledgeBase
    ): void {
        abort_unless(
            $knowledgeBase->organization_id ===
                $request->user()->organization_id,
            403
        );
    }

    /**
     * Liste des entrées de la base de connaissances.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $entries = KnowledgeBase::query()
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
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('category', 'like', "%{$search}%")
                            ->orWhere('content', 'like', "%{$search}%");
                    });
                }
            )

            ->when(
                $request->filled('category'),
                function ($query) use ($request) {
                    $query->where(
                        'category',
                        $request->input('category')
                    );
                }
            )

            ->orderBy('category')
            ->orderBy('title')
            ->paginate(15)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | Statistiques
        |--------------------------------------------------------------------------
        */

        $entriesQuery = KnowledgeBase::query()
            ->where(
                'organization_id',
                $user->organization_id
            );

        $totalEntries = (clone $entriesQuery)->count();

        $activeEntries = (clone $entriesQuery)
            ->where('is_active', true)
            ->count();

        $inactiveEntries = $totalEntries - $activeEntries;

        $categoriesCount = (clone $entriesQuery)
            ->whereNotNull('category')
            ->distinct()
            ->count('category');

        /*
        |--------------------------------------------------------------------------
        | Liste des catégories utilisées (pour le filtre)
        |--------------------------------------------------------------------------
        */

        $categories = KnowledgeBase::query()
            ->where(
                'organization_id',
                $user->organization_id
            )
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return Inertia::render('KnowledgeBase/Index', [
            'entries' => $entries,

            'categories' => $categories,

            'statistics' => [
                'total' => $totalEntries,
                'active' => $activeEntries,
                'inactive' => $inactiveEntries,
                'categories' => $categoriesCount,
            ],

            'filters' => [
                'search' => $request->input('search'),
                'category' => $request->input('category'),
            ],
        ]);
    }

    /**
     * Formulaire de création.
     */
    public function create(): Response
    {
        return Inertia::render('KnowledgeBase/Create');
    }

    /**
     * Enregistrer une entrée.
     */
    public function store(
        StoreKnowledgeBaseRequest $request
    ): RedirectResponse {
        $user = $request->user();

        $data = $request->validated();

        $data['is_active'] = $request->boolean('is_active', true);

        $user->organization
            ->knowledgeBases()
            ->create($data);

        return redirect()
            ->route('knowledge-base.index')
            ->with(
                'success',
                'Entrée ajoutée à la base de connaissances.'
            );
    }

    /**
     * Formulaire de modification.
     */
    public function edit(
        Request $request,
        KnowledgeBase $knowledgeBase
    ): Response {
        $this->authorizeKnowledgeBase(
            $request,
            $knowledgeBase
        );

        return Inertia::render('KnowledgeBase/Edit', [
            'entry' => $knowledgeBase,
        ]);
    }

    /**
     * Modifier une entrée.
     */
    public function update(
        UpdateKnowledgeBaseRequest $request,
        KnowledgeBase $knowledgeBase
    ): RedirectResponse {
        $this->authorizeKnowledgeBase(
            $request,
            $knowledgeBase
        );

        $data = $request->validated();

        $data['is_active'] = $request->boolean('is_active', true);

        $knowledgeBase->update($data);

        return redirect()
            ->route('knowledge-base.index')
            ->with(
                'success',
                'Entrée modifiée avec succès.'
            );
    }

    /**
     * Supprimer une entrée.
     */
    public function destroy(
        Request $request,
        KnowledgeBase $knowledgeBase
    ): RedirectResponse {
        $this->authorizeKnowledgeBase(
            $request,
            $knowledgeBase
        );

        $knowledgeBase->delete();

        return redirect()
            ->route('knowledge-base.index')
            ->with(
                'success',
                'Entrée supprimée avec succès.'
            );
    }

    /**
     * Activer ou désactiver une entrée.
     *
     * Une entrée désactivée n'est plus jamais transmise
     * à l'IA : elle reste visible dans l'administration
     * mais n'influence plus les réponses données aux clients.
     */
    public function toggle(
        Request $request,
        KnowledgeBase $knowledgeBase
    ): RedirectResponse {
        $this->authorizeKnowledgeBase(
            $request,
            $knowledgeBase
        );

        $knowledgeBase->update([
            'is_active' => !$knowledgeBase->is_active,
        ]);

        return back()->with(
            'success',
            $knowledgeBase->is_active
                ? 'Entrée activée avec succès.'
                : 'Entrée désactivée avec succès.'
        );
    }
}
