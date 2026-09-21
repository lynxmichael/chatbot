<?php

namespace App\Services;

use App\Models\KnowledgeBase;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Création d'une entreprise cliente.
 *
 * Réunit en un seul endroit tout ce qu'il faut pour qu'un nouveau
 * client soit opérationnel : l'entreprise, son propriétaire, son jeton
 * de widget, et quelques fiches pour que l'assistant ne soit pas muet
 * le premier jour.
 *
 * Deux chemins y mènent — l'inscription publique et l'espace
 * d'administration — et ils doivent produire exactement le même
 * résultat.
 */
class OrganizationProvisioner
{
    /**
     * @return array{organization: Organization, owner: User}
     */
    public function create(
        string $companyName,
        string $ownerName,
        string $email,
        string $password,
        ?string $phone = null
    ): array {
        return DB::transaction(function () use (
            $companyName,
            $ownerName,
            $email,
            $password,
            $phone
        ) {
            $organization = Organization::create([
                'name' => $companyName,
                'slug' => $this->slug($companyName),
                'email' => $email,
                'phone' => $phone,
                'status' => 'active',

                /*
                 * Le jeton identifie l'entreprise auprès du widget et
                 * des webhooks. Il est public par nature, mais doit
                 * rester imprévisible.
                 */
                'widget_token' => Str::random(60),

                'ai_settings' => ['plan' => 'free'],
            ]);

            $owner = User::create([
                'organization_id' => $organization->id,
                'name' => $ownerName,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'owner',
                'is_active' => true,
                'is_available' => true,
                'max_open_tickets' => 15,
            ]);

            $this->seedKnowledge($organization);

            return ['organization' => $organization, 'owner' => $owner];
        });
    }

    /**
     * Identifiant lisible, garanti unique.
     */
    private function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'entreprise';

        $slug = $base;

        $suffix = 1;

        while (
            Organization::query()
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base . '-' . ++$suffix;
        }

        return $slug;
    }

    /**
     * Trois fiches de départ.
     *
     * Une base vide donne un assistant qui transfère tout à un agent —
     * exactement ce que le produit est censé éviter. Ces fiches sont
     * volontairement incomplètes : leur rôle est de montrer la forme
     * attendue et d'inviter à les corriger.
     */
    private function seedKnowledge(Organization $organization): void
    {
        $fiches = [
            [
                'title' => "Horaires d'ouverture",
                'category' => 'general',
                'content' => "À COMPLÉTER — indiquez vos horaires réels.\n\n"
                    . "Exemple : nous sommes ouverts du lundi au samedi, "
                    . "de 8h à 18h. Fermé le dimanche et les jours fériés.",
            ],
            [
                'title' => 'Nous contacter',
                'category' => 'general',
                'content' => "À COMPLÉTER — adresse, téléphone, email.\n\n"
                    . "L'assistant donnera ces informations telles quelles : "
                    . "vérifiez-les avant d'ouvrir le service à vos clients.",
            ],
            [
                'title' => 'Délais de livraison',
                'category' => 'livraison',
                'content' => "À COMPLÉTER — délais et zones desservies.\n\n"
                    . "Si vous ne livrez pas, désactivez cette fiche plutôt "
                    . "que de la laisser vide : l'assistant ne doit jamais "
                    . "promettre ce que vous ne faites pas.",
            ],
        ];

        foreach ($fiches as $fiche) {
            KnowledgeBase::create(array_merge($fiche, [
                'organization_id' => $organization->id,

                /*
                 * Désactivées au départ : mieux vaut un assistant qui
                 * dit ne pas savoir qu'un assistant qui annonce
                 * « À COMPLÉTER » à un client.
                 */
                'is_active' => false,
            ]));
        }
    }
}
