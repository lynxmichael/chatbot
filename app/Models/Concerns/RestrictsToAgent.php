<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Restriction du champ de vision d'un agent.
 *
 * Deux modes, réglables par entreprise dans ai_settings.visibility :
 *
 * « own »  : un agent ne voit que ce qui lui est attribué, plus ce qui
 *            n'est attribué à personne. Le mode par défaut.
 *
 * « team » : tout le monde voit tout. Adapté à une petite équipe de
 *            deux ou trois personnes qui se relaient.
 *
 * Le non-attribué reste visible dans les deux cas, et ce n'est pas un
 * détail : sans cela, une demande sans responsable serait invisible de
 * tous, et personne ne la traiterait jamais.
 *
 * Le responsable de l'entreprise voit toujours l'ensemble : il ne peut
 * pas superviser ce qu'il ne voit pas.
 */
trait RestrictsToAgent
{
    /**
     * Colonne portant l'attribution. Redéfinissable par modèle.
     */
    public function agentColumn(): string
    {
        return 'assigned_to';
    }

    /**
     * Limite la requête à ce que cet utilisateur a le droit de voir.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (!$user || $user->isSuperAdmin()) {
            return $query;
        }

        /*
         * Propriétaire, administrateur, superviseur : tous voient
         * l'ensemble de l'activité de leur entreprise.
         */
        if ($user->hasAbility('records.view.all')) {
            return $query;
        }

        $organization = $user->organization;

        if ($this->visibilityMode($organization) === 'team') {
            return $query;
        }

        $column = $query->getModel()->getTable()
            . '.' . $this->agentColumn();

        return $query->where(function (Builder $inner) use ($column, $user) {
            $inner->where($column, $user->id)
                ->orWhereNull($column);
        });
    }

    /**
     * Cet utilisateur peut-il ouvrir cet enregistrement ?
     */
    public function isVisibleTo(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($this->organization_id !== $user->organization_id) {
            return false;
        }

        if ($user->hasAbility('records.view.all')) {
            return true;
        }

        if ($this->visibilityMode($user->organization) === 'team') {
            return true;
        }

        $assigned = $this->{$this->agentColumn()};

        return $assigned === null || $assigned === $user->id;
    }

    private function visibilityMode(?Organization $organization): string
    {
        $mode = $organization
            ? ($organization->aiSettings()['visibility'] ?? null)
            : null;

        $mode ??= config('ai.visibility', 'own');

        return in_array($mode, ['own', 'team'], true) ? $mode : 'own';
    }
}
