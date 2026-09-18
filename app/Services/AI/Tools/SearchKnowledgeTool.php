<?php

namespace App\Services\AI\Tools;

use App\Models\KnowledgeBase;
use Illuminate\Support\Str;

class SearchKnowledgeTool implements Tool
{
    public function name(): string
    {
        return 'search_knowledge';
    }

    public function description(): string
    {
        return "Recherche des informations officielles de l'entreprise : "
            . "horaires, produits, prix, disponibilité, procédures, "
            . "conditions, FAQ. À utiliser AVANT de répondre à toute question "
            . "factuelle sur l'entreprise. Plusieurs recherches successives "
            . "avec des mots différents sont possibles.";
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Mots-clés de la recherche, '
                        . 'par exemple « horaires samedi » ou « frais de livraison Abidjan ».',
                ],
                'category' => [
                    'type' => 'string',
                    'description' => 'Catégorie à filtrer si elle est connue. Facultatif.',
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function isWrite(): bool
    {
        return false;
    }

    public function isSafeInAssistMode(): bool
    {
        return true;
    }

    public function handle(array $input, ToolContext $context): array
    {
        $query = trim((string) ($input['query'] ?? ''));

        $category = $input['category'] ?? null;

        $entries = KnowledgeBase::query()
            ->where('organization_id', $context->organization->id)
            ->where('is_active', true)
            ->with('images:id,knowledge_base_id,caption')
            ->when($category, function ($builder) use ($category) {
                $builder->where('category', $category);
            })
            ->get();

        if ($entries->isEmpty()) {
            return [
                'found' => false,
                'message' => 'Aucune fiche disponible dans la base de connaissances.',
            ];
        }

        /*
         * Scoring simple par mots-clés : on privilégie les fiches
         * qui contiennent le plus de termes de la recherche.
         *
         * Le titre pèse plus lourd que le contenu.
         */
        $terms = collect(
            preg_split('/[^\p{L}\p{N}]+/u', Str::lower($query), -1, PREG_SPLIT_NO_EMPTY)
        )
            ->filter(fn ($term) => mb_strlen($term) >= 3)
            ->unique()
            ->values();

        $scored = $entries->map(function (KnowledgeBase $entry) use ($terms) {
            $title = Str::lower($entry->title);

            $content = Str::lower($entry->content);

            $score = 0;

            foreach ($terms as $term) {
                if (str_contains($title, $term)) {
                    $score += 3;
                }

                $score += min(3, substr_count($content, $term));
            }

            return [
                'entry' => $entry,
                'score' => $score,
            ];
        });

        $matches = $scored
            ->filter(fn ($row) => $row['score'] > 0)
            ->sortByDesc('score')
            ->take(4);

        /*
         * Aucune correspondance : on renvoie les titres disponibles
         * afin que le modèle puisse reformuler sa recherche
         * au lieu d'inventer une réponse.
         */
        if ($matches->isEmpty()) {
            return [
                'found' => false,
                'message' => "Aucune fiche ne correspond à cette recherche.",
                'available_topics' => $entries
                    ->take(25)
                    ->map(fn (KnowledgeBase $entry) => [
                        'title' => $entry->title,
                        'category' => $entry->category,
                    ])
                    ->values()
                    ->all(),
            ];
        }

        return [
            'found' => true,
            'results' => $matches
                ->map(function ($row) {
                    $entry = $row['entry'];

                    $result = [
                        'title' => $entry->title,
                        'category' => $entry->category,
                        'content' => Str::limit($entry->content, 2000),
                    ];

                    /*
                     * Les images disponibles sont annoncées avec leur
                     * identifiant : c'est ce qui permet à l'assistant de
                     * les envoyer via send_images, sans jamais inventer
                     * de référence.
                     */
                    if ($entry->images->isNotEmpty()) {
                        $result['images'] = $entry->images
                            ->map(fn ($image) => [
                                'id' => $image->id,
                                'caption' => $image->caption ?: $entry->title,
                            ])
                            ->all();
                    }

                    return $result;
                })
                ->values()
                ->all(),
        ];
    }
}
