<?php

/*
|--------------------------------------------------------------------------
| Signature des webhooks vocaux
|--------------------------------------------------------------------------
|
| Sans ce contrôle, n'importe qui connaissant l'URL peut simuler un appel
| entrant : faire sonner les postes, créer des conversations, consommer
| des appels au modèle.
|
*/

function twilioSignature(string $url, array $parameters, string $token): string
{
    ksort($parameters);

    $payload = $url;

    foreach ($parameters as $key => $value) {
        $payload .= $key . $value;
    }

    return base64_encode(hash_hmac('sha1', $payload, $token, true));
}

beforeEach(function () {
    config([
        'services.twilio.auth_token' => 'jeton-de-test',
        'services.twilio.webhook_base_url' => 'http://localhost',
    ]);

    /*
     * Ces tests portent sur la signature, pas sur les plafonds.
     */
    config(['ai.quota.voice_calls' => -1]);
});

it('refuse une requête sans signature', function () {
    $organization = makeOrganization();

    $this->post("/api/voice/{$organization->widget_token}/incoming", [
        'From' => '+2250700000000',
        'CallSid' => 'CA123',
    ])->assertStatus(403);
});

it('refuse une signature falsifiée', function () {
    $organization = makeOrganization();

    $this->withHeader('X-Twilio-Signature', 'signature-inventee')
        ->post("/api/voice/{$organization->widget_token}/incoming", [
            'From' => '+2250700000000',
            'CallSid' => 'CA123',
        ])
        ->assertStatus(403);
});

it('accepte une signature valide', function () {
    $organization = makeOrganization();

    makeAgent($organization);

    $path = "/api/voice/{$organization->widget_token}/incoming";

    $parameters = [
        'From' => '+2250700000000',
        'CallSid' => 'CA123',
    ];

    $signature = twilioSignature(
        'http://localhost' . $path,
        $parameters,
        'jeton-de-test'
    );

    $this->withHeader('X-Twilio-Signature', $signature)
        ->post($path, $parameters)
        ->assertOk()
        ->assertSee('<Gather', false);
});

it('refuse un jeton d\'organisation inconnu', function () {
    $path = '/api/voice/jeton-inexistant/incoming';

    $parameters = ['From' => '+2250700000000', 'CallSid' => 'CA999'];

    $signature = twilioSignature(
        'http://localhost' . $path,
        $parameters,
        'jeton-de-test'
    );

    $this->withHeader('X-Twilio-Signature', $signature)
        ->post($path, $parameters)
        ->assertStatus(404);
});
