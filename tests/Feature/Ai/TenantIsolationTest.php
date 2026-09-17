<?php

use App\Models\KnowledgeBase;
use App\Models\Ticket;
use App\Services\AI\Autopilot\AutopilotPolicy;
use App\Services\AI\Tools\GetTicketStatusTool;
use App\Services\AI\Tools\SearchKnowledgeTool;
use App\Services\AI\Tools\ToolContext;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Étanchéité entre organisations
|--------------------------------------------------------------------------
|
| Le test le plus important du projet. Une fuite ici signifie qu'un
| assistant révèle à un client les données d'une autre entreprise.
| C'est la faute qui tue un produit multi-entreprises.
|
*/

function contextFor($organization, $client = null): ToolContext
{
    return new ToolContext(
        organization: $organization,
        policy: AutopilotPolicy::forOrganization($organization),
        conversation: null,
        client: $client,
        message: null,
        channel: 'widget',
    );
}

it('ne lit jamais la base de connaissances d\'une autre organisation', function () {
    $mienne = makeOrganization();

    $autre = makeOrganization();

    KnowledgeBase::create([
        'organization_id' => $autre->id,
        'title' => 'Tarifs confidentiels',
        'content' => 'Nos remises grands comptes atteignent 40 pour cent.',
        'is_active' => true,
    ]);

    $result = app(SearchKnowledgeTool::class)->handle(
        ['query' => 'tarifs remises'],
        contextFor($mienne)
    );

    expect($result['found'])->toBeFalse()
        ->and(json_encode($result))->not->toContain('40 pour cent');
});

it('trouve bien ses propres fiches', function () {
    $organization = makeOrganization();

    KnowledgeBase::create([
        'organization_id' => $organization->id,
        'title' => 'Horaires d\'ouverture',
        'content' => 'Ouvert du lundi au samedi de 8h à 18h.',
        'is_active' => true,
    ]);

    $result = app(SearchKnowledgeTool::class)->handle(
        ['query' => 'horaires ouverture'],
        contextFor($organization)
    );

    expect($result['found'])->toBeTrue()
        ->and($result['results'][0]['title'])->toBe('Horaires d\'ouverture');
});

it('ignore les fiches désactivées', function () {
    $organization = makeOrganization();

    KnowledgeBase::create([
        'organization_id' => $organization->id,
        'title' => 'Promotion terminée',
        'content' => 'Moins trente pour cent sur tout le catalogue.',
        'is_active' => false,
    ]);

    $result = app(SearchKnowledgeTool::class)->handle(
        ['query' => 'promotion catalogue'],
        contextFor($organization)
    );

    expect($result['found'])->toBeFalse();
});

it('refuse de montrer le ticket d\'un autre client', function () {
    $organization = makeOrganization();

    $proprietaire = makeClient($organization);

    $curieux = makeClient($organization);

    $ticket = Ticket::create([
        'organization_id' => $organization->id,
        'client_id' => $proprietaire->id,
        'ticket_number' => 'TCK-' . Str::upper(Str::random(8)),
        'subject' => 'Litige de facturation',
        'description' => 'Montant contesté de 150 000 FCFA.',
        'category' => 'facturation',
        'status' => 'open',
        'priority' => 'high',
        'channel' => 'widget',
    ]);

    $result = app(GetTicketStatusTool::class)->handle(
        ['ticket_number' => $ticket->ticket_number],
        contextFor($organization, $curieux)
    );

    expect($result['found'])->toBeFalse()
        ->and(json_encode($result))->not->toContain('150 000');
});

it('montre son ticket au bon client', function () {
    $organization = makeOrganization();

    $client = makeClient($organization);

    $ticket = Ticket::create([
        'organization_id' => $organization->id,
        'client_id' => $client->id,
        'ticket_number' => 'TCK-' . Str::upper(Str::random(8)),
        'subject' => 'Commande en retard',
        'description' => 'Livraison attendue depuis lundi.',
        'category' => 'livraison',
        'status' => 'open',
        'priority' => 'normal',
        'channel' => 'widget',
    ]);

    $result = app(GetTicketStatusTool::class)->handle(
        ['ticket_number' => $ticket->ticket_number],
        contextFor($organization, $client)
    );

    expect($result['found'])->toBeTrue()
        ->and($result['ticket']['subject'])->toBe('Commande en retard');
});
