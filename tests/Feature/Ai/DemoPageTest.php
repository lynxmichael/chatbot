<?php

/*
|--------------------------------------------------------------------------
| Page de démonstration par entreprise
|--------------------------------------------------------------------------
|
| Chaque entreprise a son propre lien pour essayer son widget. La page
| générique /widget-test ne servait qu'à une seule, celle dont le jeton
| figurait dans le fichier .env.
|
*/

it('ouvre la démonstration d\'une entreprise avec son jeton', function () {
    $organization = makeOrganization(['name' => 'Hôtel Lagune']);

    $this->get("/demo/{$organization->widget_token}")
        ->assertOk()
        ->assertSee('Hôtel Lagune')
        ->assertSee($organization->widget_token, false);
});

it('charge le widget de l\'entreprise et non celui du fichier .env', function () {
    config(['services.widget.token' => 'jeton-global-du-env']);

    $organization = makeOrganization();

    $page = $this->get("/demo/{$organization->widget_token}")->getContent();

    expect($page)->toContain('data-token="' . $organization->widget_token . '"')
        ->and($page)->not->toContain('jeton-global-du-env');
});

it('applique la couleur de l\'entreprise', function () {
    $organization = makeOrganization();

    $organization->update(['brand_color' => '#0EA5E9']);

    $this->get("/demo/{$organization->widget_token}")
        ->assertSee('--brand: #0EA5E9', false);
});

it('répond introuvable pour un jeton inconnu', function () {
    $this->get('/demo/' . str_repeat('x', 40))->assertNotFound();
});

it('ferme la démonstration d\'une entreprise suspendue', function () {
    /*
     * Sans ce contrôle, une entreprise suspendue pourrait continuer à
     * faire tourner son assistant par ce lien.
     */
    $organization = makeOrganization(['status' => 'suspended']);

    $this->get("/demo/{$organization->widget_token}")->assertNotFound();
});

it('interdit l\'indexation par les moteurs de recherche', function () {
    $organization = makeOrganization();

    $this->get("/demo/{$organization->widget_token}")
        ->assertSee('noindex, nofollow', false);
});

it('donne le lien au responsable dans sa page Apparence', function () {
    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $this->actingAs($owner)
        ->get('/branding')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->where(
                'demo_url',
                route('demo.show', $organization->widget_token)
            )
        );
});
