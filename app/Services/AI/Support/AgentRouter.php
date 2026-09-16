<?php

namespace App\Services\AI\Support;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Choisit l'agent le mieux placé pour traiter une demande.
 *
 * Critères, dans l'ordre :
 *
 * 1. disponibilité (actif et disponible) ;
 * 2. compétence sur la catégorie ;
 * 3. charge de travail la plus faible ;
 * 4. capacité maximale non dépassée.
 */
class AgentRouter
{
    /**
     * Retourne l'identifiant de l'agent choisi, ou null
     * si aucun agent n'est disponible.
     */
    public function pick(
        int $organizationId,
        ?string $category = null,
        string $priority = 'normal'
    ): ?int {
        $agents = $this->availableAgents($organizationId);

        if ($agents->isEmpty()) {
            /*
             * Aucun agent disponible : on retombe sur les agents actifs,
             * même marqués indisponibles, plutôt que de laisser
             * la demande sans responsable.
             */
            $agents = User::query()
                ->where('organization_id', $organizationId)
                ->whereIn('role', ['agent', 'owner'])
                ->where('is_active', true)
                ->get();
        }

        if ($agents->isEmpty()) {
            return null;
        }

        $load = $this->openTicketsPerAgent($organizationId);

        $scored = $agents->map(function (User $agent) use ($load, $category, $priority) {
            $openTickets = (int) ($load[$agent->id] ?? 0);

            $capacity = (int) ($agent->max_open_tickets ?: 15);

            $skills = is_array($agent->skills) ? $agent->skills : [];

            $hasSkill = $category
                && in_array($category, $skills, true);

            /*
             * Score : plus il est bas, meilleur est le candidat.
             */
            $score = $openTickets;

            if ($hasSkill) {
                $score -= 100;
            }

            /*
             * Un agent au-delà de sa capacité est fortement pénalisé,
             * sans être totalement exclu.
             */
            if ($openTickets >= $capacity) {
                $score += 1000;
            }

            /*
             * Sur une demande urgente, la compétence prime encore plus.
             */
            if ($priority === 'urgent' && $hasSkill) {
                $score -= 50;
            }

            return [
                'id' => $agent->id,
                'score' => $score,
            ];
        });

        return $scored
            ->sortBy('score')
            ->first()['id'] ?? null;
    }

    /**
     * Agents actifs et disponibles de l'organisation.
     */
    public function availableAgents(int $organizationId): Collection
    {
        return User::query()
            ->where('organization_id', $organizationId)
            ->whereIn('role', ['agent', 'owner'])
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('is_available', true)
                    ->orWhereNull('is_available');
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * Nombre de tickets ouverts par agent.
     */
    public function openTicketsPerAgent(int $organizationId): array
    {
        return Ticket::query()
            ->where('organization_id', $organizationId)
            ->whereIn('status', ['open', 'pending', 'in_progress'])
            ->whereNotNull('assigned_to')
            ->selectRaw('assigned_to, COUNT(*) as total')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to')
            ->all();
    }
}
