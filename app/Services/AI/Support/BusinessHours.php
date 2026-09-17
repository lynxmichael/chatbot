<?php

namespace App\Services\AI\Support;

use App\Models\Organization;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Le service est-il ouvert, et y a-t-il quelqu'un pour décrocher ?
 *
 * Deux questions distinctes qu'on a tendance à confondre. Il est 14h et
 * l'entreprise est ouverte, mais les trois agents sont déjà en ligne :
 * transférer l'appel revient à le perdre. À 2h du matin, l'entreprise
 * est fermée et personne ne décrochera jamais.
 *
 * Dans les deux cas, l'IA doit le savoir AVANT de promettre un
 * transfert au client.
 */
class BusinessHours
{
    public function __construct(
        private readonly AgentRouter $router
    ) {
    }

    /**
     * Réglages horaires, redéfinissables par organisation.
     */
    public function settings(?Organization $organization = null): array
    {
        $defaults = config('ai.business_hours', []);

        if (!$organization) {
            return $defaults;
        }

        $custom = $organization->aiSettings()['business_hours'] ?? null;

        return array_merge(
            $defaults,
            is_array($custom) ? $custom : []
        );
    }

    /**
     * Sommes-nous dans les heures ouvrées ?
     */
    public function isOpen(
        ?Organization $organization = null,
        ?CarbonInterface $at = null
    ): bool {
        $settings = $this->settings($organization);

        $moment = Carbon::parse($at ?? now())
            ->setTimezone($settings['timezone'] ?? config('app.timezone'));

        if (!in_array($moment->dayOfWeekIso, $settings['days'] ?? [], true)) {
            return false;
        }

        $start = $moment->copy()
            ->setTimeFromTimeString($settings['start'] ?? '08:00');

        $end = $moment->copy()
            ->setTimeFromTimeString($settings['end'] ?? '18:00');

        return $moment->betweenIncluded($start, $end);
    }

    /**
     * Prochaine ouverture.
     */
    public function nextOpening(
        ?Organization $organization = null,
        ?CarbonInterface $from = null
    ): Carbon {
        $settings = $this->settings($organization);

        $timezone = $settings['timezone'] ?? config('app.timezone');

        $cursor = Carbon::parse($from ?? now())->setTimezone($timezone);

        $days = $settings['days'] ?? [1, 2, 3, 4, 5];

        $start = $settings['start'] ?? '08:00';

        /*
         * Encore ouvert aujourd'hui : la prochaine ouverture, c'est
         * maintenant.
         */
        if ($this->isOpen($organization, $cursor)) {
            return $cursor;
        }

        $openingToday = $cursor->copy()->setTimeFromTimeString($start);

        if (
            in_array($cursor->dayOfWeekIso, $days, true)
            && $cursor->lt($openingToday)
        ) {
            return $openingToday;
        }

        /*
         * Sinon, le prochain jour ouvré. Huit itérations suffisent :
         * au-delà, la configuration ne contient aucun jour ouvré.
         */
        for ($i = 1; $i <= 8; $i++) {
            $candidate = $cursor->copy()
                ->addDays($i)
                ->setTimeFromTimeString($start);

            if (in_array($candidate->dayOfWeekIso, $days, true)) {
                return $candidate;
            }
        }

        return $cursor->copy()->addDay()->setTimeFromTimeString($start);
    }

    /**
     * Un conseiller peut-il réellement prendre la main maintenant ?
     *
     * Exige l'ouverture du service ET un agent libre. Un agent déjà en
     * ligne ou en sonnerie ne compte pas.
     */
    public function hasReachableAgent(Organization $organization): bool
    {
        if (!$this->isOpen($organization)) {
            return false;
        }

        return $this->router->pick($organization->id) !== null
            && $this->freeAgentsCount($organization) > 0;
    }

    /**
     * Agents disponibles et non occupés par un appel.
     */
    public function freeAgentsCount(Organization $organization): int
    {
        $busy = \App\Models\Call::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', ['ringing', 'answered'])
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->all();

        return $this->router
            ->availableAgents($organization->id)
            ->reject(fn ($agent) => in_array($agent->id, $busy, true))
            ->count();
    }

    /**
     * Formulation destinée au client, en français courant.
     *
     * « demain à 8h » se comprend mieux que « 2026-09-18 08:00 ».
     */
    public function nextOpeningInWords(?Organization $organization = null): string
    {
        $opening = $this->nextOpening($organization);

        $now = Carbon::now($opening->timezone);

        $hour = $opening->format('H\hi') === $opening->format('H\h00')
            ? $opening->format('G\h')
            : $opening->format('G\hi');

        if ($opening->isSameDay($now)) {
            return 'aujourd\'hui à ' . $hour;
        }

        if ($opening->isSameDay($now->copy()->addDay())) {
            return 'demain à ' . $hour;
        }

        $days = [
            1 => 'lundi',
            2 => 'mardi',
            3 => 'mercredi',
            4 => 'jeudi',
            5 => 'vendredi',
            6 => 'samedi',
            7 => 'dimanche',
        ];

        return ($days[$opening->dayOfWeekIso] ?? '') . ' à ' . $hour;
    }
}
