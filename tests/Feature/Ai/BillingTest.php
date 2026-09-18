<?php

use App\Models\Payment;
use App\Models\Subscription;
use App\Services\AI\Usage\UsageMeter;
use App\Services\Billing\Billing;
use Carbon\Carbon;

/*
|--------------------------------------------------------------------------
| Abonnements et encaissement
|--------------------------------------------------------------------------
|
| La règle qui gouverne tout : la formule ne change qu'après
| confirmation de l'argent reçu, jamais avant.
|
*/

beforeEach(function () {
    config(['ai.billing.provider' => 'manual']);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('n\'accorde rien tant que le règlement n\'est pas confirmé', function () {
    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $this->actingAs($owner)
        ->post('/subscription/checkout', ['plan' => 'pro'])
        ->assertRedirect();

    expect(Payment::first()->status)->toBe('pending')
        // La formule n'a pas bougé.
        ->and($organization->fresh()->plan())->toBe('free')
        ->and(Subscription::count())->toBe(0);
});

it('active la formule à la confirmation', function () {
    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $this->actingAs($owner)->post('/subscription/checkout', ['plan' => 'pro']);

    app(Billing::class)->confirm(Payment::first());

    $organization->refresh();

    expect($organization->plan())->toBe('pro')
        ->and(Payment::first()->status)->toBe('paid')
        ->and(Subscription::first()->status)->toBe('active')
        ->and(app(UsageMeter::class)->allows($organization, 'voice_calls'))
        ->toBeTrue();
});

it('ignore une confirmation reçue deux fois', function () {
    /*
     * Les prestataires renvoient régulièrement la même notification.
     * Deux confirmations ne doivent pas offrir deux mois.
     */
    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $this->actingAs($owner)->post('/subscription/checkout', ['plan' => 'pro']);

    $payment = Payment::first();

    $billing = app(Billing::class);

    $first = $billing->confirm($payment);

    $second = $billing->confirm($payment->fresh());

    expect(Subscription::where('status', 'active')->count())->toBe(1)
        ->and($second->ends_at->toDateString())
        ->toBe($first->ends_at->toDateString());
});

it('prolonge depuis l\'échéance quand le client paie en avance', function () {
    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    Carbon::setTestNow(Carbon::parse('2026-10-01 10:00'));

    $billing = app(Billing::class);

    $this->actingAs($owner)->post('/subscription/checkout', ['plan' => 'pro']);
    $billing->confirm(Payment::latest('id')->first());

    // Échéance au 31 octobre. Le client règle le 20.
    Carbon::setTestNow(Carbon::parse('2026-10-20 10:00'));

    $this->actingAs($owner)->post('/subscription/checkout', ['plan' => 'pro']);
    $billing->confirm(Payment::latest('id')->first());

    $active = Subscription::where('status', 'active')->first();

    /*
     * Les onze jours restants ne sont pas perdus : la nouvelle
     * échéance part de l'ancienne.
     */
    expect($active->ends_at->toDateString())->toBe('2026-11-30');
});

it('refuse de facturer la formule gratuite', function () {
    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $this->actingAs($owner)
        ->post('/subscription/checkout', ['plan' => 'free'])
        ->assertSessionHas('error');

    expect(Payment::count())->toBe(0);
});

it('refuse une formule inconnue', function () {
    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $this->actingAs($owner)
        ->post('/subscription/checkout', ['plan' => 'formule-inventee'])
        ->assertSessionHasErrors('plan');
});

it('réserve l\'espace abonnement au responsable', function () {
    $organization = makeOrganization();

    $agent = makeAgent($organization, ['role' => 'agent']);

    $this->actingAs($agent)->get('/subscription')->assertStatus(403);
});

it('conserve les exceptions accordées lors du passage au payant', function () {
    $organization = makeOrganization();

    $organization->update([
        'ai_settings' => ['plan' => 'free', 'tone' => 'chaleureux et direct'],
    ]);

    $owner = makeAgent($organization, ['role' => 'owner']);

    $this->actingAs($owner)->post('/subscription/checkout', ['plan' => 'pro']);

    app(Billing::class)->confirm(Payment::first());

    $organization->refresh();

    expect($organization->plan())->toBe('pro')
        ->and($organization->aiSettings()['tone'])->toBe('chaleureux et direct');
});

it('rebascule en gratuit après le délai de tolérance', function () {
    $organization = makeOrganization();

    makeAgent($organization, ['role' => 'owner']);

    config(['ai.billing.grace_days' => 3]);

    Subscription::create([
        'organization_id' => $organization->id,
        'plan' => 'pro',
        'status' => 'active',
        'amount' => 25000,
        'currency' => 'XOF',
        'starts_at' => now()->subDays(35),
        'ends_at' => now()->subDays(5),
    ]);

    $organization->update(['ai_settings' => ['plan' => 'pro']]);

    $this->artisan('ai:subscriptions')->assertSuccessful();

    expect($organization->fresh()->plan())->toBe('free')
        ->and(Subscription::first()->status)->toBe('expired');
});

it('laisse le service actif pendant le délai de tolérance', function () {
    $organization = makeOrganization();

    makeAgent($organization, ['role' => 'owner']);

    config(['ai.billing.grace_days' => 3]);

    Subscription::create([
        'organization_id' => $organization->id,
        'plan' => 'pro',
        'status' => 'active',
        'amount' => 25000,
        'currency' => 'XOF',
        'starts_at' => now()->subDays(31),
        'ends_at' => now()->subDay(),
    ]);

    $organization->update(['ai_settings' => ['plan' => 'pro']]);

    $this->artisan('ai:subscriptions')->assertSuccessful();

    expect($organization->fresh()->plan())->toBe('pro');
});

it('ne prévient qu\'une fois avant l\'échéance', function () {
    $organization = makeOrganization();

    makeAgent($organization, ['role' => 'owner']);

    Subscription::create([
        'organization_id' => $organization->id,
        'plan' => 'pro',
        'status' => 'active',
        'amount' => 25000,
        'currency' => 'XOF',
        'starts_at' => now()->subDays(27),
        'ends_at' => now()->addDays(3),
    ]);

    $this->artisan('ai:subscriptions');
    $this->artisan('ai:subscriptions');

    expect(
        \Illuminate\Support\Facades\DB::table('notifications')->count()
    )->toBe(1);
});

it('confirme un règlement depuis la ligne de commande', function () {
    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $this->actingAs($owner)->post('/subscription/checkout', ['plan' => 'business']);

    $reference = Payment::first()->reference;

    $this->artisan('ai:payments', ['reference' => $reference])
        ->expectsConfirmation('Confirmer la réception de ce règlement ?', 'yes')
        ->assertSuccessful();

    expect($organization->fresh()->plan())->toBe('business');
});
