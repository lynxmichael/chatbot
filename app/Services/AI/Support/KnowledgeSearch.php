<?php

namespace App\Services\AI\Support;

use App\Models\KnowledgeBase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Recherche dans la base de connaissances.
 *
 * La première version comparait des fragments de texte. « mer » était
 * alors trouvé dans « merci » et « commercial » ; « sur » comptait
 * autant que « mer » ; et les légendes des photos — souvent exactement
 * les mots du client — n'étaient pas lues. Résultat mesuré en réel :
 * « vue sur mer » remontait « Responsable du service client » et ratait
 * la fiche des chambres.
 *
 * Cette version :
 *
 * - compare des mots entiers, pas des morceaux ;
 * - ignore les mots vides du français ;
 * - rapproche singulier et pluriel, accents et casse ;
 * - lit le titre, le contenu, la catégorie et les légendes des photos,
 *   chacun avec son propre poids ;
 * - récompense une fiche qui contient la question mot pour mot.
 */
class KnowledgeSearch
{
    /**
     * Mots trop courants pour distinguer une fiche d'une autre.
     */
    private const STOP_WORDS = [
        'a', 'au', 'aux', 'avec', 'ce', 'ces', 'cet', 'cette', 'dans', 'de',
        'des', 'du', 'elle', 'en', 'est', 'et', 'il', 'ils', 'je', 'la',
        'le', 'les', 'leur', 'leurs', 'lui', 'ma', 'mais', 'me', 'mes',
        'moi', 'mon', 'ne', 'nos', 'notre', 'nous', 'on', 'ou', 'par',
        'pas', 'pour', 'qu', 'que', 'qui', 'sa', 'se', 'ses', 'son', 'sur',
        'ta', 'te', 'tes', 'toi', 'ton', 'tu', 'un', 'une', 'vos', 'votre',
        'vous', 'y', 'est', 'sont', 'suis', 'avez', 'avoir', 'etre', 'fait',
        'faire', 'peux', 'peut', 'pouvez', 'voudrais', 'veux', 'voir',
        'donner', 'donne', 'bonjour', 'svp', 'merci', 'quel', 'quelle',
        'quels', 'quelles', 'comment', 'combien',
    ];

    /**
     * Poids de chaque source.
     *
     * La légende d'une photo pèse lourd : c'est une description courte
     * et précise, écrite pour dire ce que l'image montre.
     */
    private const WEIGHTS = [
        'title' => 5,
        'caption' => 4,
        'category' => 2,
        'content' => 1,
    ];

    /**
     * @return Collection<int, array{entry: KnowledgeBase, score: float}>
     */
    public function search(Collection $entries, string $query, int $limit = 4): Collection
    {
        $terms = $this->terms($query);

        if ($terms->isEmpty()) {
            return collect();
        }

        $phrase = $this->normalize($query);

        return $entries
            ->map(fn (KnowledgeBase $entry) => [
                'entry' => $entry,
                'score' => $this->score($entry, $terms, $phrase),
            ])
            ->filter(fn (array $row) => $row['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    private function score(KnowledgeBase $entry, Collection $terms, string $phrase): float
    {
        $captions = $entry->relationLoaded('images')
            ? $entry->images->pluck('caption')->filter()->implode(' . ')
            : '';

        $fields = [
            'title' => $entry->title,
            'caption' => $captions,
            'category' => (string) $entry->category,
            'content' => $entry->content,
        ];

        $score = 0.0;

        $matched = [];

        foreach ($fields as $field => $text) {
            if ($text === '') {
                continue;
            }

            $words = $this->words($text);

            foreach ($terms as $term) {
                $hits = $words->filter(fn ($word) => $word === $term)->count();

                if ($hits === 0) {
                    continue;
                }

                $matched[$term] = true;

                /*
                 * Au-delà de trois occurrences, un mot répété ne rend
                 * pas une fiche plus pertinente : il la rend bavarde.
                 */
                $score += self::WEIGHTS[$field] * min(3, $hits);
            }

            /*
             * La question retrouvée mot pour mot vaut beaucoup :
             * « vue sur mer » dans une légende est un signal bien plus
             * fort que « vue », « sur » et « mer » éparpillés.
             */
            if (mb_strlen($phrase) >= 5 && str_contains($this->normalize($text), $phrase)) {
                $score += self::WEIGHTS[$field] * 4;
            }
        }

        /*
         * Couverture : une fiche qui répond à tous les mots de la
         * question passe devant une fiche qui n'en reprend qu'un seul,
         * même souvent.
         */
        $coverage = count($matched) / max(1, $terms->count());

        return round($score * (0.5 + $coverage), 2);
    }

    /**
     * Mots significatifs de la question.
     */
    public function terms(string $query): Collection
    {
        /*
         * Les mots vides sont écartés AVANT la réduction au singulier :
         * dans l'autre ordre, « vous » deviendrait « vou » et passerait
         * le filtre.
         */
        return collect(
            preg_split('/[^a-z0-9]+/', $this->normalize($query), -1, PREG_SPLIT_NO_EMPTY)
        )
            ->reject(fn ($word) => in_array($word, self::STOP_WORDS, true))
            ->filter(fn ($word) => mb_strlen($word) >= 2)
            ->map(fn ($word) => $this->stem($word))
            ->unique()
            ->values();
    }

    /**
     * Découpe un texte en mots comparables.
     */
    private function words(string $text): Collection
    {
        $normalized = $this->normalize($text);

        return collect(preg_split('/[^a-z0-9]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($word) => $this->stem($word));
    }

    /**
     * Minuscules, sans accents.
     */
    private function normalize(string $text): string
    {
        return Str::of($text)->lower()->ascii()->toString();
    }

    /**
     * Rapproche singulier et pluriel.
     *
     * Volontairement minimal : « chambres » et « chambre », « plats »
     * et « plat », « tarifs » et « tarif ». Une racinisation plus
     * agressive confondrait des mots qui n'ont rien à voir.
     */
    private function stem(string $word): string
    {
        if (mb_strlen($word) > 3 && (str_ends_with($word, 's') || str_ends_with($word, 'x'))) {
            return mb_substr($word, 0, -1);
        }

        return $word;
    }
}
