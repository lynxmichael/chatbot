<?php

namespace App\Services\AI\Tools;

use App\Models\KnowledgeImage;

/**
 * Envoi de photos au client.
 *
 * L'assistant ne choisit pas une image au hasard : il ne peut envoyer
 * que celles rattachées aux fiches de la base de connaissances, dont
 * les identifiants lui ont été fournis par une recherche. C'est ce qui
 * garantit qu'un client demandant une chambre ne reçoit pas la photo
 * d'un plat.
 */
class SendImagesTool implements Tool
{
    /**
     * Au-delà, la conversation devient un catalogue illisible.
     */
    private const MAX_IMAGES = 4;

    public function name(): string
    {
        return 'send_images';
    }

    public function description(): string
    {
        return "Envoie au client des photos issues de la base de "
            . "connaissances. À utiliser quand il demande à voir quelque "
            . "chose — une chambre, un plat, un produit, un lieu — ou "
            . "quand une image explique mieux qu'un texte. "
            . "N'utilise que les identifiants d'images retournés par "
            . "search_knowledge : tout autre identifiant est refusé. "
            . "Quatre photos au maximum, et accompagne toujours l'envoi "
            . "d'une phrase.";
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'image_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Identifiants des images à envoyer, '
                        . 'tels que fournis par search_knowledge.',
                ],
            ],
            'required' => ['image_ids'],
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
        /*
         * Au téléphone, une image n'a aucun sens. Plutôt que de laisser
         * l'assistant croire qu'il a envoyé quelque chose, on le lui
         * dit franchement.
         */
        if ($context->channel === 'phone') {
            return [
                'sent' => false,
                'message' => "Vous êtes au téléphone : le client ne peut "
                    . "rien voir. Décris-lui plutôt ce qu'il demande, et "
                    . "propose de lui envoyer les photos par écrit.",
            ];
        }

        $ids = array_slice(
            array_filter(
                array_map('intval', (array) ($input['image_ids'] ?? [])),
                fn ($id) => $id > 0
            ),
            0,
            self::MAX_IMAGES
        );

        if (empty($ids)) {
            return [
                'sent' => false,
                'message' => 'Aucun identifiant d\'image fourni.',
            ];
        }

        /*
         * Filtrage par organisation : une image ne peut jamais sortir
         * de l'entreprise à laquelle elle appartient.
         */
        $images = KnowledgeImage::query()
            ->where('organization_id', $context->organization->id)
            ->whereIn('id', $ids)
            ->with('entry:id,title,is_active')
            ->get()
            /*
             * Une fiche désactivée ne doit plus rien montrer : c'est
             * souvent le cas d'une offre terminée.
             */
            ->filter(fn (KnowledgeImage $image) => $image->entry?->is_active)
            ->values();

        if ($images->isEmpty()) {
            return [
                'sent' => false,
                'message' => "Aucune de ces images n'est disponible. "
                    . "Refais une recherche pour obtenir des identifiants "
                    . "valides, ou réponds sans image.",
            ];
        }

        $attachments = $images
            ->map(fn (KnowledgeImage $image) => [
                'id' => $image->id,
                'url' => $image->url(),
                'caption' => $image->caption ?: $image->entry?->title,
            ])
            ->all();

        /*
         * Les pièces jointes s'accumulent sur le tour en cours : le
         * modèle peut appeler l'outil deux fois avant de conclure.
         */
        $context->recordEffect(
            'attachments',
            array_merge($context->effects['attachments'] ?? [], $attachments)
        );

        return [
            'sent' => true,
            'count' => count($attachments),
            'captions' => array_column($attachments, 'caption'),
            'message' => count($attachments) . ' photo(s) jointe(s) à ta '
                . 'réponse. Présente-les en une phrase : le client les voit '
                . 'sous ton message.',
        ];
    }
}
