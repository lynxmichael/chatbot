<?php

use App\Services\AI\Support\SlaCalculator;
use Carbon\Carbon;

/*
|--------------------------------------------------------------------------
| Calcul des SLA
|--------------------------------------------------------------------------
|
| Les cas qui comptent sont les bords : fin de journée, jour non ouvré,
| et franchissement de plusieurs jours. Un SLA faux se remarque des
| semaines plus tard, quand les statistiques sont déjà fausses.
|
*/

beforeEach(function () {
    config([
        'ai.business_hours.timezone' => 'Africa/Abidjan',
        'ai.business_hours.days' => [1, 2, 3, 4, 5, 6],
        'ai.business_hours.start' => '08:00',
        'ai.business_hours.end' => '18:00',
        'ai.sla.first_response' => [
            'low' => 480,
            'normal' => 240,
            'high' => 60,
            'urgent' => 15,
        ],
    ]);
});

it('reste dans la journée quand le délai le permet', function () {
    $due = app(SlaCalculator::class)->firstResponseDueAt(
        'urgent',
        Carbon::parse('2026-09-18 17:30', 'Africa/Abidjan')
    );

    expect($due->format('Y-m-d H:i'))->toBe('2026-09-18 17:45');
});

it('reporte au lendemain quand la journée se termine', function () {
    // 30 minutes avant la fermeture, puis 210 minutes le lendemain.
    $due = app(SlaCalculator::class)->firstResponseDueAt(
        'normal',
        Carbon::parse('2026-09-18 17:30', 'Africa/Abidjan')
    );

    expect($due->format('Y-m-d H:i'))->toBe('2026-09-19 11:30');
});

it('saute les jours non ouvrés', function () {
    // Le 20 septembre 2026 est un dimanche.
    $due = app(SlaCalculator::class)->firstResponseDueAt(
        'low',
        Carbon::parse('2026-09-20 10:00', 'Africa/Abidjan')
    );

    expect($due->format('Y-m-d H:i'))->toBe('2026-09-21 16:00');
});

it('attend l\'ouverture quand la demande arrive la nuit', function () {
    $due = app(SlaCalculator::class)->firstResponseDueAt(
        'high',
        Carbon::parse('2026-09-18 03:00', 'Africa/Abidjan')
    );

    expect($due->format('Y-m-d H:i'))->toBe('2026-09-18 09:00');
});

it('calcule la part de SLA consommée', function () {
    $calculator = app(SlaCalculator::class);

    Carbon::setTestNow(Carbon::parse('2026-09-18 12:00'));

    $ratio = $calculator->consumedRatio(
        Carbon::parse('2026-09-18 10:00'),
        Carbon::parse('2026-09-18 14:00')
    );

    expect($ratio)->toBe(0.5);

    Carbon::setTestNow();
});
