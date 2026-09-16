<?php

namespace Database\Seeders;

use App\Models\KnowledgeBase;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class KnowledgeBaseSeeder extends Seeder
{
    /**
     * Quelques entrées d'exemple pour tester le pipeline IA
     * de bout en bout. À adapter avec le vrai contenu de
     * l'entreprise avant mise en production.
     */
    public function run(): void
    {
        $organization = Organization::query()
            ->where('slug', 'ai-service-client')
            ->first()
            ?? Organization::query()->first();

        if (!$organization) {
            $this->command?->warn(
                'Aucune organisation trouvée : le seeder KnowledgeBaseSeeder a été ignoré.'
            );

            return;
        }

        $entries = [
            [
                'title' => 'Horaires d’ouverture',
                'category' => 'horaires',
                'content' => 'Nous sommes ouverts du lundi au vendredi de 8h à 18h, '
                    . 'et le samedi de 9h à 13h. Fermé le dimanche et les jours fériés.',
            ],
            [
                'title' => 'Moyens de paiement acceptés',
                'category' => 'paiement',
                'content' => 'Nous acceptons le paiement par Mobile Money (Orange Money, '
                    . 'MTN Money, Moov Money), par carte bancaire et en espèces à la livraison.',
            ],
            [
                'title' => 'Délais de livraison',
                'category' => 'livraison',
                'content' => 'Les commandes passées à Abidjan sont livrées sous 24 à 48h ouvrées. '
                    . 'Pour les autres villes de Côte d’Ivoire, comptez 3 à 5 jours ouvrés.',
            ],
            [
                'title' => 'Suivre ma commande',
                'category' => 'commandes',
                'content' => 'Pour suivre une commande, le client peut communiquer son numéro '
                    . 'de commande. Le statut possible est : en préparation, expédiée, livrée.',
            ],
            [
                'title' => 'Politique de retour',
                'category' => 'procedures',
                'content' => 'Un produit peut être retourné dans un délai de 7 jours après '
                    . 'réception, à condition qu’il soit non utilisé et dans son emballage d’origine.',
            ],
            [
                'title' => 'Contact du service client',
                'category' => 'general',
                'content' => 'Le service client est joignable via ce chat, par WhatsApp ou par '
                    . 'email. Un conseiller humain peut être sollicité à tout moment.',
            ],
        ];

        foreach ($entries as $entry) {
            KnowledgeBase::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'title' => $entry['title'],
                ],
                [
                    'category' => $entry['category'],
                    'content' => $entry['content'],
                    'is_active' => true,
                ]
            );
        }
    }
}
