<?php

use App\Models\Call;
use App\Models\Conversation;
use App\Models\KnowledgeBase;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Services\Billing\Billing;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Corrections issues de l'audit
|--------------------------------------------------------------------------
*/

afterEach(fn () => Carbon::setTestNow());

/*
| 1. Retour du prestataire
*/

it('vérifie la transaction annoncée, pas la dernière en attente', function () {
    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    // Deux règlements ouverts : un abandon, puis un second.
    $this->actingAs($owner)->post('/subscription/checkout', ['plan' => 'pro']);
    $abandonne = Payment::latest('id')->first();

    $this->actingAs($owner)->post('/subscription/checkout', ['plan' => 'business']);

    /*
     * Le retour porte sur le premier. La version précédente aurait
     * vérifié le second, le plus récent.
     */
    $this->actingAs($owner)
        ->get('/subscription/return?transaction_id=' . $abandonne->reference)
        ->assertRedirect();

    // Mode manuel : rien n'est encore payé, donc rien n'est accordé.
    expect($organization->fresh()->plan())->toBe('free');
});

it('refuse un retour sans référence', function () {
    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $this->actingAs($owner)->post('/subscription/checkout', ['plan' => 'pro']);

    $this->actingAs($owner)
        ->get('/subscription/return')
        ->assertSessionHas('warning');

    expect($organization->fresh()->plan())->toBe('free');
});

/*
| 2 et 3. Souscriptions
*/

it('interdit deux souscriptions actives en base', function () {
    $organization = makeOrganization();

    Subscription::create([
        'organization_id' => $organization->id,
        'plan' => 'pro',
        'status' => 'active',
        'amount' => 25000,
        'currency' => 'XOF',
        'starts_at' => now(),
        'ends_at' => now()->addDays(30),
    ]);

    expect(fn () => Subscription::create([
        'organization_id' => $organization->id,
        'plan' => 'business',
        'status' => 'active',
        'amount' => 75000,
        'currency' => 'XOF',
        'starts_at' => now(),
        'ends_at' => now()->addDays(30),
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('fait partir la nouvelle période de l\'ancienne échéance', function () {
    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    Carbon::setTestNow(Carbon::parse('2026-10-01 10:00'));

    $billing = app(Billing::class);

    $this->actingAs($owner)->post('/subscription/checkout', ['plan' => 'pro']);
    $billing->confirm(Payment::latest('id')->first());

    // Renouvellement anticipé le 20, échéance en cours au 31.
    Carbon::setTestNow(Carbon::parse('2026-10-20 10:00'));

    $this->actingAs($owner)->post('/subscription/checkout', ['plan' => 'pro']);
    $billing->confirm(Payment::latest('id')->first());

    $active = Subscription::acrossOrganizations()
        ->where('status', 'active')
        ->first();

    expect($active->starts_at->toDateString())->toBe('2026-10-31')
        ->and($active->ends_at->toDateString())->toBe('2026-11-30');
});

/*
| 4. Limite des photos
*/

it('compte les photos existantes dans la limite de huit', function () {
    Storage::fake('public');

    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $entry = KnowledgeBase::create([
        'organization_id' => $organization->id,
        'title' => 'Nos chambres',
        'content' => 'Description.',
        'is_active' => true,
    ]);

    $envoyer = fn (int $nombre) => $this->actingAs($owner)->post(
        "/knowledge/{$entry->id}/images",
        [
            'images' => collect(range(1, $nombre))
                ->map(fn ($i) => UploadedFile::fake()->image("photo{$i}.jpg"))
                ->all(),
        ]
    );

    $envoyer(5)->assertRedirect();
    $envoyer(3)->assertRedirect();

    expect($entry->images()->count())->toBe(8);

    // La neuvième est refusée.
    $envoyer(1)->assertSessionHasErrors('images');

    expect($entry->images()->count())->toBe(8);
});

/*
| 6 et 7. Appels
*/

it('applique aux appels la même visibilité qu\'aux conversations', function () {
    $organization = makeOrganization();

    $michel = makeAgent($organization, ['role' => 'agent']);

    $awa = makeAgent($organization, ['role' => 'agent']);

    $client = makeClient($organization);

    $sien = Call::create([
        'organization_id' => $organization->id,
        'client_id' => $client->id,
        'user_id' => $michel->id,
        'type' => 'incoming',
        'status' => 'completed',
        'phone' => '+2250700000000',
        'duration' => 60,
        'started_at' => now(),
    ]);

    $autre = Call::create([
        'organization_id' => $organization->id,
        'client_id' => $client->id,
        'user_id' => $awa->id,
        'type' => 'incoming',
        'status' => 'completed',
        'phone' => '+2250700000001',
        'duration' => 60,
        'started_at' => now(),
    ]);

    $libre = Call::create([
        'organization_id' => $organization->id,
        'client_id' => $client->id,
        'user_id' => null,
        'type' => 'incoming',
        'status' => 'missed',
        'phone' => '+2250700000002',
        'duration' => 0,
        'started_at' => now(),
    ]);

    $this->actingAs($michel)->get("/calls/{$sien->id}")->assertOk();
    // Un appel sans agent doit rester reprenable.
    $this->actingAs($michel)->get("/calls/{$libre->id}")->assertOk();
    $this->actingAs($michel)->get("/calls/{$autre->id}")->assertStatus(403);
});

it('accepte « completed » comme état d\'appel saisi à la main', function () {
    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $client = makeClient($organization);

    $this->actingAs($owner)
        ->post('/calls', [
            'client_id' => $client->id,
            'type' => 'outgoing',
            'status' => 'completed',
            'phone' => '+2250700000000',
            'duration' => 120,
            'started_at' => now()->toDateTimeString(),
        ])
        ->assertRedirect();

    expect(Call::acrossOrganizations()->latest('id')->first()->status)
        ->toBe('completed');
});

/*
| 8 et 9. Tickets
*/

it('empêche un agent d\'attribuer un ticket à un collègue', function () {
    $organization = makeOrganization();

    $michel = makeAgent($organization, ['role' => 'agent']);

    $awa = makeAgent($organization, ['role' => 'agent']);

    $client = makeClient($organization);

    $ticket = Ticket::create([
        'organization_id' => $organization->id,
        'client_id' => $client->id,
        'assigned_to' => $michel->id,
        'ticket_number' => 'TCK-' . Str::upper(Str::random(8)),
        'subject' => 'Incident',
        'description' => 'Description.',
        'category' => 'general',
        'status' => 'open',
        'priority' => 'normal',
        'channel' => 'widget',
    ]);

    $this->actingAs($michel)
        ->put("/tickets/{$ticket->id}", [
            'client_id' => $client->id,
            'subject' => 'Incident',
            'description' => 'Description.',
            'category' => 'general',
            'status' => 'open',
            'priority' => 'normal',
            'channel' => 'widget',
            'assigned_to' => $awa->id,
        ])
        ->assertStatus(403);

    expect($ticket->fresh()->assigned_to)->toBe($michel->id);
});

it('laisse le responsable répartir librement', function () {
    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $awa = makeAgent($organization, ['role' => 'agent']);

    $client = makeClient($organization);

    $ticket = Ticket::create([
        'organization_id' => $organization->id,
        'client_id' => $client->id,
        'ticket_number' => 'TCK-' . Str::upper(Str::random(8)),
        'subject' => 'Incident',
        'description' => 'Description.',
        'category' => 'general',
        'status' => 'open',
        'priority' => 'normal',
        'channel' => 'widget',
    ]);

    $this->actingAs($owner)
        ->put("/tickets/{$ticket->id}", [
            'client_id' => $client->id,
            'subject' => 'Incident',
            'description' => 'Description.',
            'category' => 'general',
            'status' => 'open',
            'priority' => 'normal',
            'channel' => 'widget',
            'assigned_to' => $awa->id,
        ])
        ->assertRedirect();

    expect($ticket->fresh()->assigned_to)->toBe($awa->id);
});

it('cloisonne les statistiques comme la liste', function () {
    $organization = makeOrganization();

    $michel = makeAgent($organization, ['role' => 'agent']);

    $awa = makeAgent($organization, ['role' => 'agent']);

    $client = makeClient($organization);

    $creer = function (?int $agent) use ($organization, $client) {
        Ticket::create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'assigned_to' => $agent,
            'ticket_number' => 'TCK-' . Str::upper(Str::random(8)),
            'subject' => 'Incident',
            'description' => 'Description.',
            'category' => 'general',
            'status' => 'open',
            'priority' => 'normal',
            'channel' => 'widget',
        ]);
    };

    $creer($michel->id);
    $creer($awa->id);
    $creer($awa->id);
    $creer(null);

    /*
     * Michel voit le sien et celui sans responsable : deux, pas quatre.
     * Un compteur qui annonce quatre au-dessus d'une liste de deux est
     * à la fois inutilisable et indiscret.
     */
    $this->actingAs($michel)
        ->get('/tickets')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->where('statistics.open', 2)
        );

    $this->actingAs(makeAgent($organization, ['role' => 'owner']))
        ->get('/tickets')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->where('statistics.open', 4)
        );
});

/*
| 10. Cloisonnement des messages
*/

it('cloisonne les messages par leur conversation', function () {
    $mienne = makeOrganization();

    $autre = makeOrganization();

    $conversation = Conversation::create([
        'organization_id' => $autre->id,
        'client_id' => makeClient($autre)->id,
        'subject' => 'Confidentiel',
        'channel' => 'web',
        'status' => 'open',
        'priority' => 'normal',
        'ai_enabled' => true,
        'last_message_at' => now(),
    ]);

    $message = Message::create([
        'conversation_id' => $conversation->id,
        'sender_type' => 'client',
        'content' => 'Montant du litige : 150 000 FCFA',
        'channel' => 'web',
    ]);

    $owner = makeAgent($mienne, ['role' => 'owner']);

    $this->actingAs($owner);

    /*
     * Un message ne porte pas d'organisation : il est protégé par sa
     * conversation, dont le cloisonnement s'applique en cascade.
     */
    expect(Message::find($message->id))->toBeNull()
        ->and(Message::count())->toBe(0);
});
