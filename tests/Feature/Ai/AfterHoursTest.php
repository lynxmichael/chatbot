<?php

use App\Models\FollowUp;
use App\Services\AI\Support\BusinessHours;
use Carbon\Carbon;

/*
|--------------------------------------------------------------------------
| Nuit et conseillers occupés
|--------------------------------------------------------------------------
|
| Le risque réel : l'IA annonce « ne quittez pas, je vous passe un
| conseiller » à deux heures du matin. Le téléphone sonne dans le vide,
| puis raccroche au nez du client. C'est pire que de ne pas décrocher.
|
*/

beforeEach(function () {
    config([
        'ai.business_hours.timezone' => 'Africa/Abidjan',
        'ai.business_hours.days' => [1, 2, 3, 4, 5, 6],
        'ai.business_hours.start' => '08:00',
        'ai.business_hours.end' => '18:00',
        'ai.quota.voice_calls' => -1,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('sait que le service est fermé la nuit', function () {
    // Vendredi 2h du matin.
    Carbon::setTestNow(Carbon::parse('2026-09-18 02:00', 'Africa/Abidjan'));

    expect(app(BusinessHours::class)->isOpen())->toBeFalse();
});

it('sait que le service est ouvert en journée', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-18 10:00', 'Africa/Abidjan'));

    expect(app(BusinessHours::class)->isOpen())->toBeTrue();
});

it('annonce la prochaine ouverture en français courant', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-18 02:00', 'Africa/Abidjan'));

    expect(app(BusinessHours::class)->nextOpeningInWords())
        ->toBe("aujourd'hui à 8h");

    // Vendredi 20h : la prochaine ouverture est samedi matin.
    Carbon::setTestNow(Carbon::parse('2026-09-18 20:00', 'Africa/Abidjan'));

    expect(app(BusinessHours::class)->nextOpeningInWords())
        ->toBe('demain à 8h');
});

it('saute le dimanche', function () {
    // Dimanche 20 septembre 2026.
    Carbon::setTestNow(Carbon::parse('2026-09-20 10:00', 'Africa/Abidjan'));

    $opening = app(BusinessHours::class)->nextOpening();

    expect($opening->format('Y-m-d H:i'))->toBe('2026-09-21 08:00');
});

it('ne considère aucun agent joignable la nuit', function () {
    $organization = makeOrganization();

    makeAgent($organization);

    Carbon::setTestNow(Carbon::parse('2026-09-18 02:00', 'Africa/Abidjan'));

    expect(app(BusinessHours::class)->hasReachableAgent($organization))
        ->toBeFalse();
});

it('considère un agent joignable en journée', function () {
    $organization = makeOrganization();

    makeAgent($organization);

    Carbon::setTestNow(Carbon::parse('2026-09-18 10:00', 'Africa/Abidjan'));

    expect(app(BusinessHours::class)->hasReachableAgent($organization))
        ->toBeTrue();
});

it('ne compte pas un agent déjà en ligne', function () {
    $organization = makeOrganization();

    $agent = makeAgent($organization);

    Carbon::setTestNow(Carbon::parse('2026-09-18 10:00', 'Africa/Abidjan'));

    \App\Models\Call::create([
        'organization_id' => $organization->id,
        'client_id' => makeClient($organization)->id,
        'user_id' => $agent->id,
        'type' => 'incoming',
        'status' => 'answered',
        'phone' => 'widget',
        'duration' => 0,
        'started_at' => now(),
    ]);

    expect(app(BusinessHours::class)->freeAgentsCount($organization))->toBe(0)
        ->and(app(BusinessHours::class)->hasReachableAgent($organization))
        ->toBeFalse();
});

it('programme un rappel plutôt qu\'un transfert hors des heures', function () {
    $organization = makeOrganization();

    makeAgent($organization);

    $client = makeClient($organization);

    $conversation = \App\Models\Conversation::create([
        'organization_id' => $organization->id,
        'client_id' => $client->id,
        'subject' => 'Litige de facturation',
        'channel' => 'phone',
        'status' => 'open',
        'priority' => 'normal',
        'ai_enabled' => true,
        'last_message_at' => now(),
    ]);

    Carbon::setTestNow(Carbon::parse('2026-09-18 02:00', 'Africa/Abidjan'));

    $context = new \App\Services\AI\Tools\ToolContext(
        organization: $organization,
        policy: \App\Services\AI\Autopilot\AutopilotPolicy::forOrganization($organization),
        conversation: $conversation,
        client: $client,
        message: null,
        channel: 'phone',
    );

    $result = app(\App\Services\AI\Tools\EscalateToHumanTool::class)->handle(
        [
            'reason' => 'Contestation de montant',
            'summary' => 'Le client conteste 150 000 FCFA.',
            'category' => 'facturation',
            'priority' => 'high',
        ],
        $context
    );

    expect($result['transfer'])->toBe('deferred')
        ->and($result['message'])->toContain('Ne promets pas')
        ->and($context->effects['deferred'])->toBeTrue();

    // Un rappel est programmé à la réouverture.
    $followUp = FollowUp::first();

    expect($followUp)->not->toBeNull()
        ->and($followUp->run_at->format('Y-m-d H:i'))->toBe('2026-09-18 08:00');
});

it('transfère immédiatement quand un conseiller est libre', function () {
    $organization = makeOrganization();

    makeAgent($organization);

    $client = makeClient($organization);

    $conversation = \App\Models\Conversation::create([
        'organization_id' => $organization->id,
        'client_id' => $client->id,
        'subject' => 'Demande complexe',
        'channel' => 'phone',
        'status' => 'open',
        'priority' => 'normal',
        'ai_enabled' => true,
        'last_message_at' => now(),
    ]);

    Carbon::setTestNow(Carbon::parse('2026-09-18 10:00', 'Africa/Abidjan'));

    $context = new \App\Services\AI\Tools\ToolContext(
        organization: $organization,
        policy: \App\Services\AI\Autopilot\AutopilotPolicy::forOrganization($organization),
        conversation: $conversation,
        client: $client,
        message: null,
        channel: 'phone',
    );

    $result = app(\App\Services\AI\Tools\EscalateToHumanTool::class)->handle(
        ['reason' => 'Geste commercial demandé', 'summary' => 'Dossier prêt.'],
        $context
    );

    expect($result['transfer'])->toBe('immediate')
        ->and(FollowUp::count())->toBe(0);
});
