<?php

use App\Jobs\ProcessIncomingMessage;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| Email entrant
|--------------------------------------------------------------------------
|
| Chaque fournisseur nomme ses champs différemment. Ces tests utilisent
| la forme réelle des charges utiles de Postmark, Mailgun et SendGrid :
| c'est le seul moyen de savoir que le rattachement marchera le jour où
| un vrai email arrive.
|
*/

beforeEach(function () {
    Queue::fake();

    config(['services.inbound_email.basic_password' => null]);
});

it('accepte la charge utile de Postmark', function () {
    $organization = makeOrganization();

    $this->postJson("/api/email/{$organization->widget_token}/inbound", [
        'From' => 'koffi@example.test',
        'FromName' => 'Koffi Yao',
        'Subject' => 'Ma commande est en retard',
        'TextBody' => "Bonjour,\n\nMa commande n'est pas arrivée.\n\nKoffi",
    ])->assertCreated();

    $client = Client::first();

    expect($client->email)->toBe('koffi@example.test')
        ->and($client->first_name)->toBe('Koffi')
        ->and(Conversation::first()->channel)->toBe('email')
        ->and(Message::first()->content)->toContain("n'est pas arrivée");
});

it('accepte la charge utile de Mailgun', function () {
    $organization = makeOrganization();

    $this->postJson("/api/email/{$organization->widget_token}/inbound", [
        'sender' => 'ama@example.test',
        'subject' => 'Question sur vos tarifs',
        'body-plain' => 'Quels sont vos prix pour Abidjan ?',
    ])->assertCreated();

    expect(Client::first()->email)->toBe('ama@example.test')
        ->and(Message::first()->content)->toContain('Abidjan');
});

it('accepte la charge utile de SendGrid', function () {
    $organization = makeOrganization();

    $this->postJson("/api/email/{$organization->widget_token}/inbound", [
        'from' => 'Sekou Traore <sekou@example.test>',
        'subject' => 'Remboursement',
        'text' => 'Je souhaite être remboursé.',
    ])->assertCreated();

    expect(Client::first()->email)->toBe('sekou@example.test')
        ->and(Client::first()->first_name)->toBe('Sekou');
});

it('retombe sur le HTML quand le texte brut manque', function () {
    $organization = makeOrganization();

    $this->postJson("/api/email/{$organization->widget_token}/inbound", [
        'From' => 'ama@example.test',
        'Subject' => 'Bonjour',
        'HtmlBody' => '<p>Première ligne</p><p>Seconde ligne</p>',
    ])->assertCreated();

    $content = Message::first()->content;

    expect($content)->toContain('Première ligne')
        ->and($content)->not->toContain('<p>');
});

it('retire l\'historique cité sous la réponse', function () {
    $organization = makeOrganization();

    $this->postJson("/api/email/{$organization->widget_token}/inbound", [
        'From' => 'koffi@example.test',
        'Subject' => 'Re: Votre demande',
        'TextBody' => "Merci pour votre retour.\n\n"
            . "Le 15 septembre 2026, Service Client a écrit :\n"
            . "> Bonjour, votre commande a été expédiée.\n"
            . "> Cordialement",
    ])->assertCreated();

    $content = Message::first()->content;

    expect($content)->toContain('Merci pour votre retour')
        ->and($content)->not->toContain('expédiée');
});

it('rattache la réponse à la conversation d\'origine', function () {
    $organization = makeOrganization();

    $token = $organization->widget_token;

    $first = $this->postJson("/api/email/{$token}/inbound", [
        'From' => 'koffi@example.test',
        'Subject' => 'Ma commande',
        'TextBody' => 'Où en est ma commande ?',
    ])->json('conversation_id');

    $second = $this->postJson("/api/email/{$token}/inbound", [
        'From' => 'koffi@example.test',
        'Subject' => 'Re: Ma commande',
        'TextBody' => 'Toujours rien reçu.',
        'X-Conversation-Id' => $first,
    ])->json('conversation_id');

    expect($second)->toBe($first)
        ->and(Conversation::count())->toBe(1);
});

it('refuse un email vide ou sans expéditeur', function () {
    $organization = makeOrganization();

    $token = $organization->widget_token;

    $this->postJson("/api/email/{$token}/inbound", [
        'Subject' => 'Sans expéditeur',
        'TextBody' => 'Contenu',
    ])->assertStatus(422);

    $this->postJson("/api/email/{$token}/inbound", [
        'From' => 'koffi@example.test',
        'Subject' => 'Vide',
        'TextBody' => '   ',
    ])->assertStatus(422);

    expect(Message::count())->toBe(0);
});

it('déclenche le traitement IA', function () {
    $organization = makeOrganization();

    $this->postJson("/api/email/{$organization->widget_token}/inbound", [
        'From' => 'koffi@example.test',
        'Subject' => 'Horaires',
        'TextBody' => 'Quels sont vos horaires ?',
    ])->assertCreated();

    Queue::assertPushed(ProcessIncomingMessage::class);
});

it('ne déclenche pas l\'IA si un agent a repris la main', function () {
    $organization = makeOrganization();

    $client = Client::create([
        'organization_id' => $organization->id,
        'first_name' => 'Koffi',
        'last_name' => 'Yao',
        'email' => 'koffi@example.test',
        'status' => 'active',
    ]);

    Conversation::create([
        'organization_id' => $organization->id,
        'client_id' => $client->id,
        'subject' => 'Ma commande',
        'channel' => 'email',
        'status' => 'open',
        'priority' => 'normal',
        'ai_enabled' => false,
        'last_message_at' => now(),
    ]);

    $this->postJson("/api/email/{$organization->widget_token}/inbound", [
        'From' => 'koffi@example.test',
        'Subject' => 'Re: Ma commande',
        'TextBody' => 'Une précision.',
    ])->assertCreated();

    Queue::assertNotPushed(ProcessIncomingMessage::class);
});

it('refuse un jeton d\'organisation inconnu', function () {
    $this->postJson('/api/email/jeton-inexistant/inbound', [
        'From' => 'koffi@example.test',
        'TextBody' => 'Bonjour',
    ])->assertStatus(404);
});

it('exige le mot de passe quand il est configuré', function () {
    config(['services.inbound_email.basic_password' => 'secret-partage']);

    $organization = makeOrganization();

    $path = "/api/email/{$organization->widget_token}/inbound";

    $payload = [
        'From' => 'koffi@example.test',
        'Subject' => 'Bonjour',
        'TextBody' => 'Question.',
    ];

    $this->postJson($path, $payload)->assertStatus(403);

    $this->withHeader(
        'Authorization',
        'Basic ' . base64_encode('webhook:mauvais-secret')
    )->postJson($path, $payload)->assertStatus(403);

    $this->withHeader(
        'Authorization',
        'Basic ' . base64_encode('webhook:secret-partage')
    )->postJson($path, $payload)->assertCreated();
});
