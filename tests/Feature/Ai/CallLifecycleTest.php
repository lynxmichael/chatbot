<?php

use App\Models\Call;

/*
|--------------------------------------------------------------------------
| Cycle de vie d'un appel
|--------------------------------------------------------------------------
|
| ringing -> answered -> completed, et les chemins qui s'en écartent.
| C'est ce cycle qui alimente les statistiques du service : un appel
| abouti classé « annulé » fausse tout.
|
*/

/*
 * Ces tests portent sur la mécanique de l'appel, pas sur les plafonds :
 * la voix est donc ouverte. Le plafond vocal lui-même est vérifié dans
 * UsageQuotaTest.
 */
beforeEach(function () {
    config(['ai.quota.voice_calls' => -1]);
});

it('fait sonner le poste d\'un agent disponible', function () {
    $organization = makeOrganization();

    $agent = makeAgent($organization);

    $response = $this->withHeader('X-Widget-Token', $organization->widget_token)
        ->postJson('/api/widget/calls', [
            'first_name' => 'Koffi',
            'last_name' => 'Yao',
            'email' => 'koffi@example.test',
        ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'ringing')
        ->assertJsonPath('agent.id', $agent->id);

    expect(Call::first()->status)->toBe('ringing');
});

it('renvoie occupé quand aucun agent n\'est joignable', function () {
    $organization = makeOrganization();

    makeAgent($organization, ['is_available' => false]);

    $this->withHeader('X-Widget-Token', $organization->widget_token)
        ->postJson('/api/widget/calls', ['first_name' => 'Ama'])
        ->assertCreated()
        ->assertJsonPath('status', 'busy');
});

it('démarre le décompte au décroché, pas à la sonnerie', function () {
    $organization = makeOrganization();

    $agent = makeAgent($organization);

    $callId = $this->withHeader('X-Widget-Token', $organization->widget_token)
        ->postJson('/api/widget/calls', ['first_name' => 'Awa'])
        ->json('call_id');

    $this->actingAs($agent)
        ->postJson("/call-desk/{$callId}/accept")
        ->assertOk()
        ->assertJsonPath('call.status', 'answered');

    $call = Call::find($callId);

    expect($call->answered_at)->not->toBeNull()
        ->and($call->status)->toBe('answered');
});

it('désactive l\'IA quand un agent prend l\'appel', function () {
    $organization = makeOrganization();

    $agent = makeAgent($organization);

    $started = $this->withHeader('X-Widget-Token', $organization->widget_token)
        ->postJson('/api/widget/calls', ['first_name' => 'Sekou'])
        ->json();

    $this->actingAs($agent)->postJson("/call-desk/{$started['call_id']}/accept");

    $conversation = \App\Models\Conversation::find($started['conversation_id']);

    expect($conversation->ai_enabled)->toBeFalse()
        ->and($conversation->assigned_to)->toBe($agent->id);
});

it('classe un appel abouti en terminé', function () {
    $organization = makeOrganization();

    $agent = makeAgent($organization);

    $callId = $this->withHeader('X-Widget-Token', $organization->widget_token)
        ->postJson('/api/widget/calls', ['first_name' => 'Fatou'])
        ->json('call_id');

    $this->actingAs($agent)->postJson("/call-desk/{$callId}/accept");

    $this->actingAs($agent)
        ->postJson("/call-desk/{$callId}/hang-up")
        ->assertOk();

    expect(Call::find($callId)->status)->toBe('completed');
});

it('classe en annulé un abandon pendant la sonnerie', function () {
    $organization = makeOrganization();

    makeAgent($organization);

    $callId = $this->withHeader('X-Widget-Token', $organization->widget_token)
        ->postJson('/api/widget/calls', ['first_name' => 'Ibrahim'])
        ->json('call_id');

    // Le client raccroche sans que personne ait décroché.
    $this->withHeader('X-Widget-Token', $organization->widget_token)
        ->postJson("/api/widget/calls/{$callId}/end")
        ->assertOk()
        ->assertJsonPath('status', 'cancelled');
});

it('ne crée pas deux appels pour la même conversation', function () {
    $organization = makeOrganization();

    makeAgent($organization);

    $token = $organization->widget_token;

    $first = $this->withHeader('X-Widget-Token', $token)
        ->postJson('/api/widget/calls', ['first_name' => 'Mariam'])
        ->json();

    $second = $this->withHeader('X-Widget-Token', $token)
        ->postJson('/api/widget/calls', [
            'conversation_id' => $first['conversation_id'],
        ]);

    $second->assertOk()
        ->assertJsonPath('call_id', $first['call_id']);

    expect(Call::count())->toBe(1);
});

it('refuse un appel sans jeton valide', function () {
    $this->postJson('/api/widget/calls', ['first_name' => 'Anonyme'])
        ->assertStatus(401);

    $this->withHeader('X-Widget-Token', 'jeton-invente')
        ->postJson('/api/widget/calls', ['first_name' => 'Anonyme'])
        ->assertStatus(401);
});

it('empêche un agent de toucher à l\'appel d\'une autre organisation', function () {
    $mienne = makeOrganization();

    $autre = makeOrganization();

    makeAgent($mienne);

    $intrus = makeAgent($autre);

    $callId = $this->withHeader('X-Widget-Token', $mienne->widget_token)
        ->postJson('/api/widget/calls', ['first_name' => 'Client'])
        ->json('call_id');

    /*
     * 404 et non 403 : le cloisonnement opère dès la résolution du
     * modèle, donc la ressource n'existe pas du point de vue de cet
     * utilisateur. C'est préférable à un 403, qui confirmerait
     * l'existence d'une ressource portant cet identifiant ailleurs.
     */
    $this->actingAs($intrus)
        ->postJson("/call-desk/{$callId}/accept")
        ->assertStatus(404);

    /*
     * La vérification passe par acrossOrganizations : depuis la session
     * de l'intrus, l'appel est invisible — ce qui est précisément la
     * preuve recherchée.
     */
    expect(
        Call::acrossOrganizations()->find($callId)->status
    )->toBe('ringing');
});
