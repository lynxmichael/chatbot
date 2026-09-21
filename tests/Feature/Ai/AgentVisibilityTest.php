<?php

use App\Models\Conversation;
use App\Models\Ticket;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Cloisonnement
|--------------------------------------------------------------------------
|
| Deux niveaux à ne pas confondre : entre entreprises, et entre agents
| d'une même entreprise. Le premier protège vos clients les uns des
| autres. Le second protège la confidentialité interne.
|
*/

function makeConversation($organization, ?int $assignedTo = null, string $subject = 'Demande')
{
    return Conversation::create([
        'organization_id' => $organization->id,
        'client_id' => makeClient($organization)->id,
        'assigned_to' => $assignedTo,
        'subject' => $subject,
        'channel' => 'web',
        'status' => 'open',
        'priority' => 'normal',
        'ai_enabled' => false,
        'last_message_at' => now(),
    ]);
}

function makeTicket($organization, ?int $assignedTo = null, string $subject = 'Incident')
{
    return Ticket::create([
        'organization_id' => $organization->id,
        'client_id' => makeClient($organization)->id,
        'assigned_to' => $assignedTo,
        'ticket_number' => 'TCK-' . Str::upper(Str::random(8)),
        'subject' => $subject,
        'description' => 'Description.',
        'category' => 'general',
        'status' => 'open',
        'priority' => 'normal',
        'channel' => 'web',
    ]);
}

/*
|--------------------------------------------------------------------------
| Entre entreprises
|--------------------------------------------------------------------------
*/

it('applique le cloisonnement sans filtre explicite', function () {
    $mienne = makeOrganization();

    $autre = makeOrganization();

    makeConversation($mienne, null, 'Chez moi');
    makeConversation($autre, null, 'Chez le voisin');

    $agent = makeAgent($mienne, ['role' => 'owner']);

    $this->actingAs($agent);

    /*
     * Requête sans aucun filtre : le cloisonnement doit opérer seul.
     */
    $subjects = Conversation::pluck('subject');

    expect($subjects)->toContain('Chez moi')
        ->and($subjects)->not->toContain('Chez le voisin');
});

it('empêche d\'ouvrir la conversation d\'une autre entreprise', function () {
    $mienne = makeOrganization();

    $autre = makeOrganization();

    $conversation = makeConversation($autre);

    $owner = makeAgent($mienne, ['role' => 'owner']);

    /*
     * 404 et non 403 : le cloisonnement s'applique dès la résolution
     * du modèle, donc la conversation n'existe tout simplement pas du
     * point de vue de cet utilisateur.
     *
     * C'est le meilleur des deux comportements : un 403 confirmerait
     * qu'une conversation portant ce numéro existe ailleurs.
     */
    $this->actingAs($owner)
        ->get("/conversations/{$conversation->id}")
        ->assertStatus(404);
});

/*
|--------------------------------------------------------------------------
| Entre agents d'une même entreprise
|--------------------------------------------------------------------------
*/

it('cache à un agent les conversations de ses collègues', function () {
    $organization = makeOrganization();

    $michel = makeAgent($organization, ['role' => 'agent']);

    $awa = makeAgent($organization, ['role' => 'agent']);

    makeConversation($organization, $michel->id, 'Dossier de Michel');
    makeConversation($organization, $awa->id, 'Dossier de Awa');
    makeConversation($organization, null, 'Personne ne l\'a prise');

    $this->actingAs($michel)
        ->get('/conversations')
        ->assertOk()
        ->assertInertia(function ($page) {
            $subjects = collect($page->toArray()['props']['conversations']['data'])
                ->pluck('subject');

            expect($subjects)->toContain('Dossier de Michel')
                // Une demande sans responsable doit rester visible,
                // sinon personne ne la traiterait.
                ->and($subjects)->toContain("Personne ne l'a prise")
                ->and($subjects)->not->toContain('Dossier de Awa');
        });
});

it('laisse le responsable voir toute son entreprise', function () {
    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $agent = makeAgent($organization, ['role' => 'agent']);

    makeConversation($organization, $agent->id, 'Dossier de l\'agent');

    $this->actingAs($owner)
        ->get('/conversations')
        ->assertOk()
        ->assertInertia(function ($page) {
            $subjects = collect($page->toArray()['props']['conversations']['data'])
                ->pluck('subject');

            expect($subjects)->toContain("Dossier de l'agent");
        });
});

it('refuse à un agent d\'ouvrir le dossier d\'un collègue', function () {
    $organization = makeOrganization();

    $michel = makeAgent($organization, ['role' => 'agent']);

    $awa = makeAgent($organization, ['role' => 'agent']);

    $conversation = makeConversation($organization, $awa->id);

    $this->actingAs($michel)
        ->get("/conversations/{$conversation->id}")
        ->assertStatus(403);
});

it('laisse un agent ouvrir une demande sans responsable', function () {
    /*
     * Le défaut corrigé : la liste montrait ces demandes, et
     * l'ouverture renvoyait une erreur.
     */
    $organization = makeOrganization();

    $agent = makeAgent($organization, ['role' => 'agent']);

    $conversation = makeConversation($organization, null);

    $this->actingAs($agent)
        ->get("/conversations/{$conversation->id}")
        ->assertOk();
});

it('applique la même règle aux tickets', function () {
    $organization = makeOrganization();

    $michel = makeAgent($organization, ['role' => 'agent']);

    $awa = makeAgent($organization, ['role' => 'agent']);

    $sien = makeTicket($organization, $michel->id);

    $autre = makeTicket($organization, $awa->id);

    $libre = makeTicket($organization, null);

    $this->actingAs($michel)->get("/tickets/{$sien->id}")->assertOk();
    $this->actingAs($michel)->get("/tickets/{$libre->id}")->assertOk();
    $this->actingAs($michel)->get("/tickets/{$autre->id}")->assertStatus(403);
});

it('ouvre tout aux agents quand l\'entreprise le demande', function () {
    /*
     * Une équipe de trois personnes qui se remplacent n'a pas besoin
     * de ce cloisonnement interne.
     */
    $organization = makeOrganization();

    $organization->update([
        'ai_settings' => ['visibility' => 'team'],
    ]);

    $michel = makeAgent($organization, ['role' => 'agent']);

    $awa = makeAgent($organization, ['role' => 'agent']);

    $conversation = makeConversation($organization, $awa->id);

    $this->actingAs($michel)
        ->get("/conversations/{$conversation->id}")
        ->assertOk();
});

it('rattache automatiquement une création à l\'entreprise', function () {
    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $this->actingAs($owner);

    $client = makeClient($organization);

    /*
     * Sans organization_id explicite : le trait doit le déduire.
     */
    $conversation = Conversation::create([
        'client_id' => $client->id,
        'subject' => 'Sans organisation explicite',
        'channel' => 'web',
        'status' => 'open',
        'priority' => 'normal',
        'ai_enabled' => true,
        'last_message_at' => now(),
    ]);

    expect($conversation->organization_id)->toBe($organization->id);
});
