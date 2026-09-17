<?php

use App\Models\KnowledgeBase;

/*
|--------------------------------------------------------------------------
| Import de la base de connaissances
|--------------------------------------------------------------------------
|
| C'est la porte d'entrée du produit : si l'import découpe mal, le
| responsable abandonne et l'assistant reste muet.
|
*/

it('découpe un texte collé en fiches', function () {
    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $this->actingAs($owner)->post('/knowledge/import', [
        'content' => "Horaires d'ouverture\n"
            . "Ouvert du lundi au samedi, de 8h à 18h.\n"
            . "Fermé le dimanche.\n\n"
            . "Frais de livraison\n"
            . "Gratuite à Abidjan au-dessus de 25 000 FCFA.",
        'category' => null,
        'replace_existing' => false,
    ])->assertRedirect(route('knowledge.index'));

    expect(KnowledgeBase::count())->toBe(2);

    $premiere = KnowledgeBase::where('title', "Horaires d'ouverture")->first();

    expect($premiere)->not->toBeNull()
        ->and($premiere->content)->toContain('lundi au samedi')
        ->and($premiere->content)->not->toContain("Horaires d'ouverture");
});

it('ignore les blocs sans contenu', function () {
    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $this->actingAs($owner)->post('/knowledge/import', [
        'content' => "Titre seul sans réponse\n\n"
            . "Vrai sujet\nAvec sa réponse.",
        'replace_existing' => false,
    ]);

    expect(KnowledgeBase::count())->toBe(1)
        ->and(KnowledgeBase::first()->title)->toBe('Vrai sujet');
});

it('refuse un contenu qui ne produit aucune fiche', function () {
    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $this->actingAs($owner)
        ->post('/knowledge/import', [
            'content' => 'Une seule ligne isolée',
            'replace_existing' => false,
        ])
        ->assertSessionHas('error');

    expect(KnowledgeBase::count())->toBe(0);
});

it('remplace la base existante seulement si demandé', function () {
    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    KnowledgeBase::create([
        'organization_id' => $organization->id,
        'title' => 'Ancienne fiche',
        'content' => 'Contenu existant.',
        'is_active' => true,
    ]);

    $this->actingAs($owner)->post('/knowledge/import', [
        'content' => "Nouvelle fiche\nNouveau contenu.",
        'replace_existing' => true,
    ]);

    expect(KnowledgeBase::count())->toBe(1)
        ->and(KnowledgeBase::first()->title)->toBe('Nouvelle fiche');
});

it('interdit l\'accès aux agents non responsables', function () {
    $organization = makeOrganization();

    $agent = makeAgent($organization, ['role' => 'agent']);

    $this->actingAs($agent)->get('/knowledge')->assertStatus(403);
});

it('ne laisse pas modifier la fiche d\'une autre organisation', function () {
    $mienne = makeOrganization();

    $autre = makeOrganization();

    $owner = makeAgent($mienne, ['role' => 'owner']);

    $fiche = KnowledgeBase::create([
        'organization_id' => $autre->id,
        'title' => 'Fiche privée',
        'content' => 'Contenu confidentiel.',
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->put("/knowledge/{$fiche->id}", [
            'title' => 'Détournée',
            'content' => 'Modifié.',
            'is_active' => true,
        ])
        ->assertStatus(403);

    expect($fiche->fresh()->title)->toBe('Fiche privée');
});
