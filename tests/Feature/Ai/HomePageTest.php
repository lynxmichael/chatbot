<?php

/*
|--------------------------------------------------------------------------
| Page d'accueil
|--------------------------------------------------------------------------
|
| La racine affichait la page de démonstration de Laravel : son logo,
| ses liens de documentation, les versions de PHP. Un prospect arrivait
| sur le framework, pas sur le produit.
|
*/

it('affiche la vitrine du produit', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Welcome')
                ->has('plans', 3)
        );
});

it('n\'expose plus les versions de Laravel et de PHP', function () {
    /*
     * Au-delà de l'image, afficher ses versions en clair aide
     * quiconque cherche une faille connue.
     */
    $this->get('/')
        ->assertInertia(
            fn ($page) => $page
                ->missing('laravelVersion')
                ->missing('phpVersion')
        );
});

it('envoie un utilisateur connecté directement sur sa console', function () {
    $user = makeAgent(makeOrganization(), ['role' => 'owner']);

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect(route('dashboard'));
});

it('n\'affiche jamais « Laravel » comme nom du produit', function () {
    config(['app.name' => 'Laravel']);

    $this->get('/')
        ->assertInertia(
            fn ($page) => $page->where('app.name', 'AI Service Client')
        );
});

it('reprend le nom configuré quand il a été changé', function () {
    config(['app.name' => 'Nexus Assist']);

    $this->get('/')
        ->assertInertia(
            fn ($page) => $page->where('app.name', 'Nexus Assist')
        );
});
