<?php

use App\Models\Conversation;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Rôles au sein d'une entreprise
|--------------------------------------------------------------------------
|
| Quatre rôles, des droits nommés plutôt que des comparaisons de chaînes
| éparpillées. Ce qui compte : qu'ajouter un rôle ne demande pas de
| toucher aux contrôleurs, et qu'aucun droit ne fuie vers un rôle qui ne
| devrait pas l'avoir.
|
*/

function makeMember($organization, string $role): User
{
    return makeAgent($organization, ['role' => $role]);
}

it('accorde tous les droits au propriétaire', function () {
    $owner = makeMember(makeOrganization(), 'owner');

    foreach (array_keys(config('roles.abilities')) as $ability) {
        expect($owner->hasAbility($ability))->toBeTrue($ability);
    }
});

it('réserve l\'abonnement au propriétaire', function () {
    /*
     * L'argent de l'entreprise ne se délègue pas à un administrateur.
     */
    $organization = makeOrganization();

    expect(makeMember($organization, 'admin')->hasAbility('billing.manage'))
        ->toBeFalse()
        ->and(makeMember($organization, 'supervisor')->hasAbility('billing.manage'))
        ->toBeFalse()
        ->and(makeMember($organization, 'agent')->hasAbility('billing.manage'))
        ->toBeFalse();
});

it('donne la supervision sans les réglages au superviseur', function () {
    $superviseur = makeMember(makeOrganization(), 'supervisor');

    expect($superviseur->hasAbility('records.view.all'))->toBeTrue()
        ->and($superviseur->hasAbility('records.assign'))->toBeTrue()
        ->and($superviseur->hasAbility('autopilot.manage'))->toBeFalse()
        ->and($superviseur->hasAbility('knowledge.manage'))->toBeFalse()
        ->and($superviseur->hasAbility('agents.manage'))->toBeFalse();
});

it('limite l\'agent à ses propres dossiers', function () {
    $agent = makeMember(makeOrganization(), 'agent');

    expect($agent->hasAbility('records.view.all'))->toBeFalse()
        ->and($agent->hasAbility('records.assign'))->toBeFalse()
        ->and($agent->hasAbility('clients.manage'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Application réelle
|--------------------------------------------------------------------------
*/

it('ouvre les réglages à l\'administrateur, pas au superviseur', function () {
    $organization = makeOrganization();

    $admin = makeMember($organization, 'admin');

    $superviseur = makeMember($organization, 'supervisor');

    $this->actingAs($admin)->get('/knowledge')->assertOk();
    $this->actingAs($admin)->get('/autopilot')->assertOk();
    $this->actingAs($admin)->get('/branding')->assertOk();

    $this->actingAs($superviseur)->get('/knowledge')->assertStatus(403);
    $this->actingAs($superviseur)->get('/autopilot')->assertStatus(403);
});

it('ferme l\'abonnement à l\'administrateur', function () {
    $organization = makeOrganization();

    $admin = makeMember($organization, 'admin');

    $owner = makeMember($organization, 'owner');

    $this->actingAs($admin)->get('/subscription')->assertStatus(403);
    $this->actingAs($owner)->get('/subscription')->assertOk();
});

it('laisse un superviseur voir toute l\'activité', function () {
    $organization = makeOrganization();

    $superviseur = makeMember($organization, 'supervisor');

    $agent = makeMember($organization, 'agent');

    $client = makeClient($organization);

    Conversation::create([
        'organization_id' => $organization->id,
        'client_id' => $client->id,
        'assigned_to' => $agent->id,
        'subject' => 'Dossier de l\'agent',
        'channel' => 'web',
        'status' => 'open',
        'priority' => 'normal',
        'ai_enabled' => false,
        'last_message_at' => now(),
    ]);

    $this->actingAs($superviseur)
        ->get('/conversations')
        ->assertOk()
        ->assertInertia(function ($page) {
            $sujets = collect($page->toArray()['props']['conversations']['data'])
                ->pluck('subject');

            expect($sujets)->toContain("Dossier de l'agent");
        });
});

it('autorise un superviseur à répartir le travail', function () {
    $organization = makeOrganization();

    $superviseur = makeMember($organization, 'supervisor');

    $agent = makeMember($organization, 'agent');

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

    $this->actingAs($superviseur)
        ->put("/tickets/{$ticket->id}", [
            'client_id' => $client->id,
            'subject' => 'Incident',
            'description' => 'Description.',
            'category' => 'general',
            'status' => 'open',
            'priority' => 'normal',
            'channel' => 'widget',
            'assigned_to' => $agent->id,
        ])
        ->assertRedirect();

    expect($ticket->fresh()->assigned_to)->toBe($agent->id);
});

it('permet à un administrateur de changer le rôle d\'un membre', function () {
    $organization = makeOrganization();

    $admin = makeMember($organization, 'admin');

    $agent = makeMember($organization, 'agent');

    $this->actingAs($admin)
        ->put("/agents/{$agent->id}", [
            'name' => $agent->name,
            'email' => $agent->email,
            'role' => 'supervisor',
            'is_available' => true,
            'max_open_tickets' => 15,
            'skills' => [],
        ])
        ->assertRedirect();

    expect($agent->fresh()->role)->toBe('supervisor');
});

it('ne laisse pas rétrograder le propriétaire', function () {
    /*
     * Une entreprise doit toujours conserver quelqu'un qui détient
     * tous les droits, faute de quoi plus personne ne peut rien régler.
     */
    $organization = makeOrganization();

    $admin = makeMember($organization, 'admin');

    $owner = makeMember($organization, 'owner');

    $this->actingAs($admin)->put("/agents/{$owner->id}", [
        'name' => $owner->name,
        'email' => $owner->email,
        'role' => 'agent',
        'is_available' => true,
        'max_open_tickets' => 15,
        'skills' => [],
    ]);

    expect($owner->fresh()->role)->toBe('owner');
});

it('refuse un rôle inventé', function () {
    $organization = makeOrganization();

    $admin = makeMember($organization, 'admin');

    $agent = makeMember($organization, 'agent');

    $this->actingAs($admin)
        ->put("/agents/{$agent->id}", [
            'name' => $agent->name,
            'email' => $agent->email,
            'role' => 'super-chef',
            'is_available' => true,
            'max_open_tickets' => 15,
            'skills' => [],
        ])
        ->assertSessionHasErrors('role');

    expect($agent->fresh()->role)->toBe('agent');
});

it('expose les droits au frontend', function () {
    /*
     * Le menu se filtre sur ces droits : sans eux, l'interface
     * proposerait des entrées menant à une erreur 403.
     */
    $organization = makeOrganization();

    $superviseur = makeMember($organization, 'supervisor');

    $this->actingAs($superviseur)
        ->get('/conversations')
        ->assertInertia(function ($page) {
            $abilities = $page->toArray()['props']['auth']['abilities'];

            expect($abilities)->toContain('records.view.all')
                ->and($abilities)->not->toContain('billing.manage');
        });
});
