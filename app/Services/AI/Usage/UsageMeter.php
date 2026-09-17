<?php

namespace App\Services\AI\Usage;

use App\Models\Organization;
use App\Models\UsagePeriod;
use Illuminate\Support\Facades\DB;

/**
 * Compteur de consommation et application des plafonds.
 *
 * Ce service existe pour une raison simple : chaque message traité par
 * l'IA coûte de l'argent, et chaque minute d'appel aussi. Sans plafond,
 * une offre gratuite qui rencontre du succès devient une facture.
 *
 * Le principe retenu : quand le plafond est atteint, l'assistant se met
 * en retrait et les demandes partent vers un agent humain. Le service
 * client continue de fonctionner — c'est l'automatisation qui s'arrête,
 * pas l'entreprise.
 */
class UsageMeter
{
    /**
     * Compteurs du mois en cours, créés au besoin.
     */
    public function current(Organization $organization): UsagePeriod
    {
        return UsagePeriod::firstOrCreate(
            [
                'organization_id' => $organization->id,
                'period' => now()->format('Y-m'),
            ],
            []
        );
    }

    /**
     * Plafonds applicables, réglages de l'organisation compris.
     */
    public function quota(Organization $organization): array
    {
        $settings = $organization->aiSettings();

        return array_merge(
            config('ai.quota', []),
            is_array($settings['quota'] ?? null) ? $settings['quota'] : []
        );
    }

    /**
     * Le plafond autorise-t-il encore ce type d'usage ?
     *
     * Un plafond à zéro signifie « interdit », une valeur négative
     * signifie « illimité ».
     */
    public function allows(Organization $organization, string $type): bool
    {
        $quota = $this->quota($organization);

        $limit = $quota[$type] ?? -1;

        if ($limit < 0) {
            return true;
        }

        if ($limit === 0) {
            return false;
        }

        return $this->consumed($organization, $type) < $limit;
    }

    public function consumed(Organization $organization, string $type): int
    {
        return (int) ($this->current($organization)->{$type} ?? 0);
    }

    public function remaining(Organization $organization, string $type): ?int
    {
        $limit = $this->quota($organization)[$type] ?? -1;

        if ($limit < 0) {
            return null;
        }

        return max(0, $limit - $this->consumed($organization, $type));
    }

    /**
     * Part du plafond consommée, entre 0 et 1. Null si illimité.
     */
    public function ratio(Organization $organization, string $type): ?float
    {
        $limit = $this->quota($organization)[$type] ?? -1;

        if ($limit <= 0) {
            return $limit === 0 ? 1.0 : null;
        }

        return round(
            min(1.0, $this->consumed($organization, $type) / $limit),
            3
        );
    }

    /**
     * Incrémente les compteurs.
     *
     * L'incrément est fait en SQL plutôt qu'en PHP : deux messages
     * traités simultanément par deux workers ne doivent pas s'écraser
     * l'un l'autre.
     */
    public function record(Organization $organization, array $metrics): void
    {
        $period = $this->current($organization);

        $increments = array_filter(
            [
                'ai_messages' => (int) ($metrics['ai_messages'] ?? 0),
                'input_tokens' => (int) ($metrics['input_tokens'] ?? 0),
                'output_tokens' => (int) ($metrics['output_tokens'] ?? 0),
                'voice_calls' => (int) ($metrics['voice_calls'] ?? 0),
                'voice_seconds' => (int) ($metrics['voice_seconds'] ?? 0),
                'conversations' => (int) ($metrics['conversations'] ?? 0),
            ],
            fn ($value) => $value > 0
        );

        if (empty($increments)) {
            return;
        }

        $updates = [];

        $bindings = [];

        foreach ($increments as $column => $value) {
            $updates[] = "{$column} = {$column} + ?";

            $bindings[] = $value;
        }

        $bindings[] = $period->id;

        DB::update(
            'update usage_periods set ' . implode(', ', $updates)
            . ', updated_at = CURRENT_TIMESTAMP where id = ?',
            $bindings
        );
    }

    /**
     * Coût estimé du mois, en dollars.
     *
     * Une estimation, pas une facture : elle sert au responsable à
     * comprendre ce que son usage représente avant de choisir un prix.
     */
    public function estimatedCost(Organization $organization): float
    {
        $period = $this->current($organization);

        $pricing = config('ai.pricing', []);

        $cost =
            ($period->input_tokens / 1_000_000)
                * (float) ($pricing['input_per_million'] ?? 0)
            + ($period->output_tokens / 1_000_000)
                * (float) ($pricing['output_per_million'] ?? 0)
            + ($period->voice_seconds / 60)
                * (float) ($pricing['voice_per_minute'] ?? 0);

        return round($cost, 4);
    }

    /**
     * Vue d'ensemble, telle qu'affichée au responsable.
     */
    public function summary(Organization $organization): array
    {
        $period = $this->current($organization);

        $quota = $this->quota($organization);

        $describe = function (string $type) use ($organization, $quota, $period) {
            $limit = $quota[$type] ?? -1;

            return [
                'used' => (int) ($period->{$type} ?? 0),
                'limit' => $limit,
                'unlimited' => $limit < 0,
                'blocked' => $limit === 0,
                'ratio' => $this->ratio($organization, $type),
                'remaining' => $this->remaining($organization, $type),
            ];
        };

        return [
            'period' => $period->period,
            'plan' => $quota['plan'] ?? 'free',

            'ai_messages' => $describe('ai_messages'),
            'voice_calls' => $describe('voice_calls'),

            'tokens' => [
                'input' => (int) $period->input_tokens,
                'output' => (int) $period->output_tokens,
            ],

            'voice_minutes' => (int) round($period->voice_seconds / 60),
            'conversations' => (int) $period->conversations,
            'estimated_cost' => $this->estimatedCost($organization),
        ];
    }

    /**
     * Faut-il prévenir le responsable ?
     *
     * Une seule alerte par seuil et par mois : un avertissement répété
     * à chaque message finit par être ignoré.
     */
    public function shouldWarn(Organization $organization): bool
    {
        $ratio = $this->ratio($organization, 'ai_messages');

        if ($ratio === null || $ratio < (float) config('ai.quota.warn_at', 0.8)) {
            return false;
        }

        $period = $this->current($organization);

        if ($period->warned_at) {
            return false;
        }

        $period->update(['warned_at' => now()]);

        return true;
    }

    public function markBlocked(Organization $organization): bool
    {
        $period = $this->current($organization);

        if ($period->blocked_at) {
            return false;
        }

        $period->update(['blocked_at' => now()]);

        return true;
    }
}
