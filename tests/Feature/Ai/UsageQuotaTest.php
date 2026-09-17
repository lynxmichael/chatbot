<?php

use App\Models\Call;
use App\Models\UsagePeriod;
use App\Services\AI\Usage\UsageMeter;

/*
|--------------------------------------------------------------------------
| Plafonds de consommation
|--------------------------------------------------------------------------
|
| Ce qui protège l'offre gratuite. Une erreur ici se paie littéralement :
| soit le service se coupe sans raison, soit la facture continue de
| grimper après le plafond.
|
*/

it('compte les incréments sans les écraser', function () {
    $organization = makeOrganization();

    $meter = app(UsageMeter::class);

    $meter->record($organization, ['ai_messages' => 1, 'input_tokens' => 500]);
    $meter->record($organization, ['ai_messages' => 1, 'input_tokens' => 300]);

    $period = UsagePeriod::first();

    expect($period->ai_messages)->toBe(2)
        ->and($period->input_tokens)->toBe(800);
});

it('autorise tant que le plafond n\'est pas atteint', function () {
    $organization = makeOrganization();

    $organization->update(['ai_settings' => ['quota' => ['ai_messages' => 3]]]);

    $meter = app(UsageMeter::class);

    expect($meter->allows($organization, 'ai_messages'))->toBeTrue();

    $meter->record($organization, ['ai_messages' => 3]);

    expect($meter->allows($organization->fresh(), 'ai_messages'))->toBeFalse();
});

it('traite zéro comme une interdiction et le négatif comme illimité', function () {
    $bloquee = makeOrganization();
    $bloquee->update(['ai_settings' => ['quota' => ['voice_calls' => 0]]]);

    $illimitee = makeOrganization();
    $illimitee->update(['ai_settings' => ['quota' => ['voice_calls' => -1]]]);

    $meter = app(UsageMeter::class);

    expect($meter->allows($bloquee, 'voice_calls'))->toBeFalse()
        ->and($meter->allows($illimitee, 'voice_calls'))->toBeTrue();
});

it('refuse un appel quand la voix est désactivée', function () {
    $organization = makeOrganization();

    // Le plafond vocal vaut zéro par défaut dans l'offre gratuite.
    makeAgent($organization);

    $this->withHeader('X-Widget-Token', $organization->widget_token)
        ->postJson('/api/widget/calls', ['first_name' => 'Koffi'])
        ->assertOk()
        ->assertJsonPath('status', 'unavailable');

    expect(Call::count())->toBe(0);
});

it('autorise l\'appel quand le plafond vocal le permet', function () {
    $organization = makeOrganization();

    $organization->update(['ai_settings' => ['quota' => ['voice_calls' => 5]]]);

    makeAgent($organization);

    $this->withHeader('X-Widget-Token', $organization->widget_token)
        ->postJson('/api/widget/calls', ['first_name' => 'Awa'])
        ->assertCreated()
        ->assertJsonPath('status', 'ringing');

    expect(app(UsageMeter::class)->consumed($organization, 'voice_calls'))
        ->toBe(1);
});

it('compte les secondes d\'appel au raccroché', function () {
    $organization = makeOrganization();

    $organization->update(['ai_settings' => ['quota' => ['voice_calls' => 5]]]);

    $agent = makeAgent($organization);

    $callId = $this->withHeader('X-Widget-Token', $organization->widget_token)
        ->postJson('/api/widget/calls', ['first_name' => 'Sekou'])
        ->json('call_id');

    $this->actingAs($agent)->postJson("/call-desk/{$callId}/accept");

    // On recule le décroché pour simuler une conversation de 90 secondes.
    Call::where('id', $callId)->update([
        'answered_at' => now()->subSeconds(90),
    ]);

    $this->actingAs($agent)->postJson("/call-desk/{$callId}/hang-up");

    expect(app(UsageMeter::class)->consumed($organization, 'voice_seconds'))
        ->toBeGreaterThanOrEqual(89);
});

it('estime le coût à partir des jetons consommés', function () {
    $organization = makeOrganization();

    config([
        'ai.pricing.input_per_million' => 3.0,
        'ai.pricing.output_per_million' => 15.0,
        'ai.pricing.voice_per_minute' => 0.0,
    ]);

    app(UsageMeter::class)->record($organization, [
        'input_tokens' => 1_000_000,
        'output_tokens' => 1_000_000,
    ]);

    expect(app(UsageMeter::class)->estimatedCost($organization))->toBe(18.0);
});

it('ne prévient qu\'une fois par seuil', function () {
    $organization = makeOrganization();

    $organization->update(['ai_settings' => ['quota' => ['ai_messages' => 10]]]);

    $meter = app(UsageMeter::class);

    $meter->record($organization, ['ai_messages' => 8]);

    expect($meter->shouldWarn($organization))->toBeTrue()
        ->and($meter->shouldWarn($organization))->toBeFalse();
});

it('sépare les compteurs de deux organisations', function () {
    $premiere = makeOrganization();

    $seconde = makeOrganization();

    $meter = app(UsageMeter::class);

    $meter->record($premiere, ['ai_messages' => 5]);

    expect($meter->consumed($premiere, 'ai_messages'))->toBe(5)
        ->and($meter->consumed($seconde, 'ai_messages'))->toBe(0);
});
