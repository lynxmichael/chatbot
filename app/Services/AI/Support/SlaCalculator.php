<?php

namespace App\Services\AI\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Calcule les échéances SLA en ne comptant que les heures ouvrées
 * définies dans config/ai.php.
 */
class SlaCalculator
{
    /**
     * Échéance de première réponse pour une priorité donnée.
     */
    public function firstResponseDueAt(
        string $priority,
        ?CarbonInterface $from = null
    ): Carbon {
        return $this->dueAt('first_response', $priority, $from);
    }

    /**
     * Échéance de résolution pour une priorité donnée.
     */
    public function resolutionDueAt(
        string $priority,
        ?CarbonInterface $from = null
    ): Carbon {
        return $this->dueAt('resolution', $priority, $from);
    }

    public function dueAt(
        string $type,
        string $priority,
        ?CarbonInterface $from = null
    ): Carbon {
        $minutes = (int) (
            config("ai.sla.{$type}.{$priority}")
            ?? config("ai.sla.{$type}.normal")
            ?? 240
        );

        return $this->addBusinessMinutes(
            $from ? Carbon::parse($from) : now(),
            $minutes
        );
    }

    /**
     * Ajoute un nombre de minutes en sautant les nuits
     * et les jours non ouvrés.
     */
    public function addBusinessMinutes(
        CarbonInterface $from,
        int $minutes
    ): Carbon {
        $timezone = config('ai.business_hours.timezone', config('app.timezone'));

        $days = config('ai.business_hours.days', [1, 2, 3, 4, 5]);

        $start = config('ai.business_hours.start', '08:00');

        $end = config('ai.business_hours.end', '18:00');

        $cursor = Carbon::parse($from)->setTimezone($timezone);

        $remaining = max(0, $minutes);

        /*
         * Garde-fou : évite toute boucle infinie si la configuration
         * ne contient aucun jour ouvré.
         */
        $guard = 0;

        while ($remaining > 0 && $guard < 1000) {
            $guard++;

            /*
             * Jour non ouvré : on saute au jour suivant.
             */
            if (!in_array($cursor->dayOfWeekIso, $days, true)) {
                $cursor = $cursor->addDay()
                    ->setTimeFromTimeString($start);

                continue;
            }

            $dayStart = $cursor->copy()->setTimeFromTimeString($start);

            $dayEnd = $cursor->copy()->setTimeFromTimeString($end);

            /*
             * Avant l'ouverture : on attend l'ouverture.
             */
            if ($cursor->lt($dayStart)) {
                $cursor = $dayStart->copy();
            }

            /*
             * Après la fermeture : on passe au lendemain.
             */
            if ($cursor->gte($dayEnd)) {
                $cursor = $cursor->addDay()
                    ->setTimeFromTimeString($start);

                continue;
            }

            $available = (int) max(
                0,
                $cursor->diffInMinutes($dayEnd, false)
            );

            if ($available >= $remaining) {
                $cursor = $cursor->addMinutes($remaining);

                $remaining = 0;

                break;
            }

            $remaining -= $available;

            $cursor = $dayEnd->copy()
                ->addDay()
                ->setTimeFromTimeString($start);
        }

        return $cursor->setTimezone(config('app.timezone'));
    }

    /**
     * Part du SLA déjà consommée, entre 0 et 1.
     * Utilisé par la supervision pour repérer les tickets à risque.
     */
    public function consumedRatio(
        ?CarbonInterface $createdAt,
        ?CarbonInterface $dueAt
    ): ?float {
        if (!$createdAt || !$dueAt) {
            return null;
        }

        $total = $createdAt->diffInSeconds($dueAt, false);

        if ($total <= 0) {
            return 1.0;
        }

        $elapsed = $createdAt->diffInSeconds(now(), false);

        return round(min(1.5, max(0, $elapsed / $total)), 2);
    }
}
