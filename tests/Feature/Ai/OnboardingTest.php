<?php

use App\Models\KnowledgeBase;
use App\Models\Organization;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Accueil d'une nouvelle entreprise
|--------------------------------------------------------------------------
|
| Le trou par lequel aucun client ne pouvait entrer : l'inscription
| créait un utilisateur sans entreprise, donc un compte devant lequel
| presque chaque page répondait « introuvable ».
|
*/

it('crée une entreprise complète à l\'inscription', function () {
    $this->post('/register', [
        'company_name' => 'Boutique Awa',
        'name' => 'Awa Koné',
        'email' => 'awa@example.test',
        'phone' => '+2250700000000',
        'password' => 'motdepasse-solide',
        'password_confirmation' => 'motdepasse-solide',
    ])->assertRedirect();

    $organization = Organization::query()->first();

    expect($organization)->not->toBeNull()
        ->and($organization->name)->toBe('Boutique Awa')
        ->and($organization->slug)->toBe('boutique-awa')
        ->and($organization->widget_token)->not->toBeEmpty();

    $owner = User::where('email', 'awa@example.test')->first();

    expect($owner->organization_id)->toBe($organization->id)
        ->and($owner->role)->toBe('owner')
        ->and($owner->hasAbility('billing.manage'))->toBeTrue();
});

it('rend le compte immédiatement utilisable', function () {
    /*
     * La vraie vérification : le propriétaire peut ouvrir les écrans
     * réservés, ce qui n'était pas le cas avant.
     */
    $this->post('/register', [
        'company_name' => 'Hôtel Lagune',
        'name' => 'Koffi Yao',
        'email' => 'koffi@example.test',
        'password' => 'motdepasse-solide',
        'password_confirmation' => 'motdepasse-solide',
    ]);

    $owner = User::where('email', 'koffi@example.test')->first();

    $this->actingAs($owner)->get('/knowledge')->assertOk();
    $this->actingAs($owner)->get('/autopilot')->assertOk();
    $this->actingAs($owner)->get('/branding')->assertOk();
    $this->actingAs($owner)->get('/subscription')->assertOk();
});

it('dépose des fiches de départ, désactivées', function () {
    /*
     * Désactivées à dessein : un assistant qui répond « À COMPLÉTER »
     * à un vrai client serait pire que muet.
     */
    $this->post('/register', [
        'company_name' => 'Restaurant Attiéké',
        'name' => 'Ama Diallo',
        'email' => 'ama@example.test',
        'password' => 'motdepasse-solide',
        'password_confirmation' => 'motdepasse-solide',
    ]);

    $fiches = KnowledgeBase::acrossOrganizations()->get();

    expect($fiches)->toHaveCount(3)
        ->and($fiches->every(fn ($fiche) => $fiche->is_active === false))
        ->toBeTrue();
});

it('exige le nom de l\'entreprise', function () {
    $this->post('/register', [
        'name' => 'Sans entreprise',
        'email' => 'sans@example.test',
        'password' => 'motdepasse-solide',
        'password_confirmation' => 'motdepasse-solide',
    ])->assertSessionHasErrors('company_name');

    expect(Organization::query()->count())->toBe(0);
});

it('distingue deux entreprises de même nom', function () {
    foreach (['a@example.test', 'b@example.test'] as $email) {
        /*
         * L'inscription connecte automatiquement : sans déconnexion,
         * la seconde tentative serait renvoyée par le middleware
         * « guest ».
         */
        auth()->logout();

        $this->post('/register', [
            'company_name' => 'Chez Tantie',
            'name' => 'Responsable',
            'email' => $email,
            'password' => 'motdepasse-solide',
            'password_confirmation' => 'motdepasse-solide',
        ]);
    }

    $slugs = Organization::query()->pluck('slug');

    expect($slugs)->toHaveCount(2)
        ->and($slugs->unique())->toHaveCount(2);
});

/*
|--------------------------------------------------------------------------
| Création depuis l'espace plateforme
|--------------------------------------------------------------------------
*/

it('laisse un administrateur inscrire une entreprise', function () {
    $admin = makeAgent(makeOrganization(), ['is_super_admin' => true]);

    $this->actingAs($admin)
        ->post('/admin/organizations', [
            'company_name' => 'Pharmacie du Plateau',
            'owner_name' => 'Michel Kouassi',
            'email' => 'michel@example.test',
            'password' => 'motdepasse-solide',
            'plan' => 'pro',
        ])
        ->assertRedirect();

    $organization = Organization::query()
        ->where('name', 'Pharmacie du Plateau')
        ->first();

    expect($organization)->not->toBeNull()
        ->and($organization->plan())->toBe('pro');

    $owner = User::where('email', 'michel@example.test')->first();

    expect($owner->role)->toBe('owner')
        ->and($owner->organization_id)->toBe($organization->id);
});

it('ferme la création d\'entreprise aux responsables clients', function () {
    $owner = makeAgent(makeOrganization(), ['role' => 'owner']);

    $this->actingAs($owner)
        ->post('/admin/organizations', [
            'company_name' => 'Entreprise fantôme',
            'owner_name' => 'Quelqu\'un',
            'email' => 'fantome@example.test',
            'password' => 'motdepasse-solide',
        ])
        ->assertStatus(403);
});

it('refuse un email déjà utilisé', function () {
    $admin = makeAgent(makeOrganization(), ['is_super_admin' => true]);

    $existant = makeAgent(makeOrganization(), ['role' => 'owner']);

    $this->actingAs($admin)
        ->post('/admin/organizations', [
            'company_name' => 'Doublon',
            'owner_name' => 'Doublon',
            'email' => $existant->email,
            'password' => 'motdepasse-solide',
        ])
        ->assertSessionHasErrors('email');
});
