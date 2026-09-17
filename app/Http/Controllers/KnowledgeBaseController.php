<?php

namespace App\Http\Controllers;

use App\Models\AiAction;
use App\Models\KnowledgeBase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Base de connaissances.
 *
 * C'est elle qui détermine la qualité des réponses de l'IA, bien plus
 * que le modèle employé. Une entreprise dont la base est vide a un
 * assistant qui ne sait rien : l'enjeu de cet écran est donc qu'un
 * responsable puisse la remplir en minutes, pas en heures.
 */
class KnowledgeBaseController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->authorizeOwner($request);

        $entries = KnowledgeBase::query()
            ->where('organization_id', $user->organization_id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search')->toString());

                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->when(
                $request->filled('category'),
                fn ($query) => $query->where('category', $request->input('category'))
            )
            ->when(
                $request->input('status') === 'inactive',
                fn ($query) => $query->where('is_active', false)
            )
            ->when(
                $request->input('status') === 'active',
                fn ($query) => $query->where('is_active', true)
            )
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (KnowledgeBase $entry) => [
                'id' => $entry->id,
                'title' => $entry->title,
                'category' => $entry->category,
                'excerpt' => Str::limit(strip_tags($entry->content), 180),
                'length' => mb_strlen($entry->content),
                'is_active' => (bool) $entry->is_active,
                'updated_at' => optional($entry->updated_at)->toDateTimeString(),
            ]);

        return Inertia::render('KnowledgeBase/Index', [
            'entries' => $entries,
            'categories' => config('ai.categories', []),

            'filters' => [
                'search' => $request->input('search', ''),
                'category' => $request->input('category', ''),
                'status' => $request->input('status', ''),
            ],

            'statistics' => [
                'total' => KnowledgeBase::query()
                    ->where('organization_id', $user->organization_id)
                    ->count(),

                'active' => KnowledgeBase::query()
                    ->where('organization_id', $user->organization_id)
                    ->where('is_active', true)
                    ->count(),
            ],

            'gaps' => $this->gaps($user->organization_id),
        ]);
    }

    /**
     * Questions auxquelles l'IA n'a pas su répondre.
     *
     * Chaque recherche infructueuse est déjà journalisée dans ai_actions.
     * Les regrouper transforme un échec en liste de fiches à écrire :
     * c'est la boucle qui fait progresser la base toute seule.
     */
    private function gaps(int $organizationId): array
    {
        /*
         * Les fonctions JSON diffèrent d'un moteur à l'autre. Plutôt que
         * d'écrire deux dialectes SQL, on rapatrie les recherches
         * infructueuses des trente derniers jours et on les regroupe en
         * PHP : le volume reste modeste et le code reste portable.
         */
        $rows = AiAction::query()
            ->where('organization_id', $organizationId)
            ->where('tool', 'search_knowledge')
            ->where('status', 'executed')
            ->where('created_at', '>=', now()->subDays(30))
            ->latest('id')
            ->limit(2000)
            ->get(['input', 'output', 'created_at']);

        $grouped = [];

        foreach ($rows as $row) {
            $found = $row->output['found'] ?? null;

            if ($found !== false) {
                continue;
            }

            $question = trim((string) ($row->input['query'] ?? ''));

            if ($question === '') {
                continue;
            }

            /*
             * Regroupement insensible à la casse : « horaires » et
             * « Horaires » sont la même lacune.
             */
            $key = Str::lower($question);

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'question' => $question,
                    'occurrences' => 0,
                    'last_seen' => optional($row->created_at)->toDateTimeString(),
                ];
            }

            $grouped[$key]['occurrences']++;
        }

        usort(
            $grouped,
            fn ($a, $b) => $b['occurrences'] <=> $a['occurrences']
        );

        return array_slice(array_values($grouped), 0, 12);
    }

    public function create(Request $request)
    {
        $this->authorizeOwner($request);

        return Inertia::render('KnowledgeBase/Form', [
            'entry' => null,
            'categories' => config('ai.categories', []),

            /*
             * Pré-remplissage depuis une question restée sans réponse.
             */
            'suggestedTitle' => $request->input('question', ''),
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->authorizeOwner($request);

        $validated = $this->validateEntry($request);

        KnowledgeBase::create([
            'organization_id' => $user->organization_id,
            'title' => $validated['title'],
            'category' => $validated['category'] ?? null,
            'content' => $validated['content'],
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('knowledge.index')
            ->with('success', 'Fiche ajoutée. L\'assistant peut déjà s\'en servir.');
    }

    public function edit(Request $request, KnowledgeBase $knowledge)
    {
        $this->authorizeOwner($request);

        $this->authorizeEntry($request, $knowledge);

        return Inertia::render('KnowledgeBase/Form', [
            'entry' => [
                'id' => $knowledge->id,
                'title' => $knowledge->title,
                'category' => $knowledge->category,
                'content' => $knowledge->content,
                'is_active' => (bool) $knowledge->is_active,
            ],

            'categories' => config('ai.categories', []),
            'suggestedTitle' => '',
        ]);
    }

    public function update(Request $request, KnowledgeBase $knowledge)
    {
        $this->authorizeOwner($request);

        $this->authorizeEntry($request, $knowledge);

        $validated = $this->validateEntry($request);

        $knowledge->update([
            'title' => $validated['title'],
            'category' => $validated['category'] ?? null,
            'content' => $validated['content'],
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('knowledge.index')
            ->with('success', 'Fiche mise à jour.');
    }

    public function toggle(Request $request, KnowledgeBase $knowledge)
    {
        $this->authorizeOwner($request);

        $this->authorizeEntry($request, $knowledge);

        $knowledge->update(['is_active' => !$knowledge->is_active]);

        return back()->with(
            'success',
            $knowledge->is_active
                ? 'Fiche activée.'
                : 'Fiche désactivée : l\'assistant ne s\'en servira plus.'
        );
    }

    public function destroy(Request $request, KnowledgeBase $knowledge)
    {
        $this->authorizeOwner($request);

        $this->authorizeEntry($request, $knowledge);

        $knowledge->delete();

        return back()->with('success', 'Fiche supprimée.');
    }

    /**
     * Import en masse.
     */
    public function importForm(Request $request)
    {
        $this->authorizeOwner($request);

        return Inertia::render('KnowledgeBase/Import', [
            'categories' => config('ai.categories', []),
        ]);
    }

    /**
     * Découpe un texte collé en fiches.
     *
     * Format attendu : un bloc par fiche, séparé par une ligne vide.
     * La première ligne du bloc devient le titre, le reste le contenu.
     * C'est exactement la forme d'une FAQ copiée depuis un document,
     * ce qui évite toute ressaisie.
     */
    public function import(Request $request)
    {
        $user = $this->authorizeOwner($request);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:200000'],

            'category' => [
                'nullable',
                Rule::in(config('ai.categories', [])),
            ],

            'replace_existing' => ['required', 'boolean'],
        ]);

        $blocks = preg_split(
            '/\R\s*\R/u',
            trim($validated['content'])
        );

        $entries = [];

        foreach ($blocks as $block) {
            $lines = preg_split('/\R/u', trim($block));

            $lines = array_values(array_filter(
                array_map('trim', $lines),
                fn ($line) => $line !== ''
            ));

            if (count($lines) < 2) {
                /*
                 * Un bloc d'une seule ligne n'est pas une fiche :
                 * il n'a pas de contenu à retourner à l'IA.
                 */
                continue;
            }

            $title = Str::limit(array_shift($lines), 250, '');

            $entries[] = [
                'organization_id' => $user->organization_id,
                'title' => $title,
                'category' => $validated['category'] ?? null,
                'content' => implode("\n", $lines),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (empty($entries)) {
            return back()->with(
                'error',
                'Aucune fiche reconnue. Chaque fiche doit comporter un titre '
                . 'sur la première ligne, puis son contenu, et être séparée '
                . 'de la suivante par une ligne vide.'
            );
        }

        DB::transaction(function () use ($entries, $user, $validated) {
            if ($validated['replace_existing']) {
                KnowledgeBase::query()
                    ->where('organization_id', $user->organization_id)
                    ->delete();
            }

            foreach (array_chunk($entries, 100) as $chunk) {
                KnowledgeBase::insert($chunk);
            }
        });

        return redirect()
            ->route('knowledge.index')
            ->with(
                'success',
                count($entries) . ' fiche(s) importée(s).'
            );
    }

    /* ------------------------------------------------------------------
     | Utilitaires
     |------------------------------------------------------------------ */

    private function validateEntry(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:250'],

            'category' => [
                'nullable',
                Rule::in(config('ai.categories', [])),
            ],

            'content' => ['required', 'string', 'max:50000'],

            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function authorizeOwner(Request $request)
    {
        $user = $request->user();

        abort_unless($user->role === 'owner', 403);

        return $user;
    }

    private function authorizeEntry(Request $request, KnowledgeBase $entry): void
    {
        abort_unless(
            $entry->organization_id === $request->user()->organization_id,
            403
        );
    }
}
