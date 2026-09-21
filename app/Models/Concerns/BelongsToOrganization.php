<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Cloisonnement entre entreprises.
 *
 * Jusqu'ici, chaque contrôleur filtrait lui-même sur organization_id.
 * Cela fonctionne tant que personne n'oublie — et il suffit d'un oubli
 * dans une méthode ajoutée plus tard pour qu'une entreprise voie les
 * données d'une autre.
 *
 * Ce trait déplace la garantie du contrôleur vers le modèle : toute
 * requête est filtrée automatiquement, y compris celles qu'on écrira
 * dans six mois.
 *
 * Le filtre ne s'applique que lorsqu'un utilisateur est authentifié :
 *
 * - en console (relances, supervision, import), il n'y a pas
 *   d'utilisateur, et les commandes parcourent volontairement toutes
 *   les entreprises ;
 * - sur les routes du widget et des webhooks, l'organisation est
 *   résolue par jeton, et le filtrage reste explicite ;
 * - un administrateur de plateforme voit tout, c'est sa fonction.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder) {
            $user = Auth::user();

            if (!$user || $user->isSuperAdmin() || !$user->organization_id) {
                return;
            }

            $builder->where(
                $builder->getModel()->getTable() . '.organization_id',
                $user->organization_id
            );
        });

        /*
         * À la création, l'organisation est déduite de l'utilisateur
         * connecté quand elle n'est pas fournie. Un oubli produit alors
         * une donnée correctement rattachée, plutôt qu'orpheline.
         */
        static::creating(function ($model) {
            if ($model->organization_id) {
                return;
            }

            $user = Auth::user();

            if ($user && !$user->isSuperAdmin() && $user->organization_id) {
                $model->organization_id = $user->organization_id;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Requête sans cloisonnement.
     *
     * Réservé aux traitements de fond qui parcourent légitimement
     * toutes les entreprises. À n'employer que dans ce cas, et jamais
     * depuis un contrôleur.
     */
    public static function acrossOrganizations(): Builder
    {
        return static::query()->withoutGlobalScope('organization');
    }
}
