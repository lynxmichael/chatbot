<?php

use App\Models\KnowledgeBase;
use App\Models\KnowledgeImage;

/*
|--------------------------------------------------------------------------
| Commande de diagnostic
|--------------------------------------------------------------------------
*/

function hotelWithPhotos(bool $active = true)
{
    $organization = makeOrganization(['name' => 'Hôtel Lagune']);

    $organization->update(['ai_settings' => ['plan' => 'business']]);

    $entry = KnowledgeBase::create([
        'organization_id' => $organization->id,
        'title' => 'Chambre vue sur mer',
        'content' => 'Chambre Deluxe avec balcon et vue sur la mer.',
        'is_active' => $active,
    ]);

    KnowledgeImage::create([
        'organization_id' => $organization->id,
        'knowledge_base_id' => $entry->id,
        'path' => 'knowledge/1/vue.jpg',
        'caption' => 'Vue sur mer',
    ]);

    return $organization;
}

it('ne signale rien quand tout est en place', function () {
    $organization = hotelWithPhotos();

    $this->artisan('ai:diagnose', [
        'organization' => $organization->id,
        '--query' => 'vue sur mer',
    ])
        ->expectsOutputToContain('Aucun problème détecté')
        ->assertSuccessful();
});

it('détecte une liste d\'outils figée', function () {
    $organization = hotelWithPhotos();

    $organization->update([
        'ai_settings' => [
            'plan' => 'business',
            'allowed_actions' => ['search_knowledge'],
        ],
    ]);

    $this->artisan('ai:diagnose', ['organization' => $organization->id])
        ->expectsOutputToContain("Liste d'outils figée")
        ->assertFailed();
});

it('détecte une fiche à photos désactivée', function () {
    $organization = hotelWithPhotos(active: false);

    $this->artisan('ai:diagnose', ['organization' => $organization->id])
        ->expectsOutputToContain('elle est désactivée')
        ->assertFailed();
});

it('détecte une question qui ne trouve pas la fiche', function () {
    $organization = hotelWithPhotos();

    $this->artisan('ai:diagnose', [
        'organization' => $organization->id,
        '--query' => 'piscine chauffée',
    ])
        ->expectsOutputToContain('Aucune fiche trouvée')
        ->assertFailed();
});

it('détecte l\'absence d\'outil en formule gratuite', function () {
    $organization = hotelWithPhotos();

    $organization->update(['ai_settings' => ['plan' => 'free']]);

    $this->artisan('ai:diagnose', ['organization' => $organization->id])
        ->expectsOutputToContain("send_images n'est pas disponible")
        ->assertFailed();
});
