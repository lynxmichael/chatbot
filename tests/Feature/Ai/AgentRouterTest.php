<?php

use App\Models\Client;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AI\Support\AgentRouter;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Routage des agents
|--------------------------------------------------------------------------
*/

function giveTickets(Organization $organization, User $agent, int $count): void
{
    $client = makeClient($organization);

    for ($i = 0; $i < $count; $i++) {
        Ticket::create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'assigned_to' => $agent->id,
            'ticket_number' => 'TCK-' . Str::upper(Str::random(8)),
            'subject' => 'Sujet',
            'description' => 'Description',
            'category' => 'general',
            'status' => 'open',
            'priority' => 'normal',
            'channel' => 'widget',
        ]);
    }
}

it('choisit l\'agent compétent plutôt que le moins chargé', function () {
    $organization = makeOrganization();

    $generaliste = makeAgent($organization, ['skills' => []]);

    $specialiste = makeAgent($organization, ['skills' => ['facturation']]);

    // Le spécialiste est plus chargé, mais reste le bon choix.
    giveTickets($organization, $specialiste, 5);

    $chosen = app(AgentRouter::class)->pick(
        $organization->id,
        'facturation'
    );

    expect($chosen)->toBe($specialiste->id)
        ->and($chosen)->not->toBe($generaliste->id);
});

it('répartit sur le moins chargé à compétence égale', function () {
    $organization = makeOrganization();

    $charge = makeAgent($organization, ['skills' => ['livraison']]);

    $libre = makeAgent($organization, ['skills' => ['livraison']]);

    giveTickets($organization, $charge, 4);

    expect(app(AgentRouter::class)->pick($organization->id, 'livraison'))
        ->toBe($libre->id);
});

it('évite un agent au-delà de sa capacité', function () {
    $organization = makeOrganization();

    $sature = makeAgent($organization, [
        'skills' => ['technique'],
        'max_open_tickets' => 2,
    ]);

    $disponible = makeAgent($organization, ['skills' => []]);

    giveTickets($organization, $sature, 3);

    expect(app(AgentRouter::class)->pick($organization->id, 'technique'))
        ->toBe($disponible->id);
});

it('ignore les agents indisponibles', function () {
    $organization = makeOrganization();

    makeAgent($organization, [
        'skills' => ['technique'],
        'is_available' => false,
    ]);

    $joignable = makeAgent($organization, ['skills' => []]);

    expect(app(AgentRouter::class)->pick($organization->id, 'technique'))
        ->toBe($joignable->id);
});

it('ne choisit jamais un agent d\'une autre organisation', function () {
    $mienne = makeOrganization();

    $autre = makeOrganization();

    makeAgent($autre, ['skills' => ['facturation']]);

    expect(app(AgentRouter::class)->pick($mienne->id, 'facturation'))
        ->toBeNull();
});
