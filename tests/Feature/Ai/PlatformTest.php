<?php

use App\Models\Payment;
use App\Models\PlatformSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Administration de la plateforme et identité visuelle
|--------------------------------------------------------------------------
*/

it('ferme l\'espace plateforme à tout le monde sauf aux administrateurs', function () {
    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $this->actingAs($owner)->get('/admin')->assertStatus(403);
    $this->actingAs($owner)->get('/admin/payments')->assertStatus(403);
    $this->actingAs($owner)->get('/admin/settings')->assertStatus(403);
});

it('ouvre l\'espace plateforme à un administrateur', function () {
    $admin = makeAgent(makeOrganization(), [
        'role' => 'owner',
        'is_super_admin' => true,
    ]);

    $this->actingAs($admin)->get('/admin')->assertOk();
});

it('montre toutes les entreprises à l\'administrateur', function () {
    $admin = makeAgent(makeOrganization(), ['is_super_admin' => true]);

    makeOrganization(['name' => 'Boutique Awa']);
    makeOrganization(['name' => 'MAKOR Telecom']);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Admin/Dashboard')
                ->where('totals.organizations', 3)
        );
});

it('confirme un règlement depuis l\'espace plateforme', function () {
    $admin = makeAgent(makeOrganization(), ['is_super_admin' => true]);

    $cliente = makeOrganization();

    $owner = makeAgent($cliente, ['role' => 'owner']);

    $this->actingAs($owner)->post('/subscription/checkout', ['plan' => 'pro']);

    $payment = Payment::first();

    $this->actingAs($admin)
        ->post("/admin/payments/{$payment->id}/confirm", ['method' => 'wave'])
        ->assertRedirect();

    expect($cliente->fresh()->plan())->toBe('pro')
        ->and($payment->fresh()->method)->toBe('wave');
});

it('interdit à un responsable de confirmer son propre règlement', function () {
    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $this->actingAs($owner)->post('/subscription/checkout', ['plan' => 'pro']);

    $payment = Payment::first();

    $this->actingAs($owner)
        ->post("/admin/payments/{$payment->id}/confirm")
        ->assertStatus(403);

    expect($organization->fresh()->plan())->toBe('free');
});

it('enregistre les coordonnées de règlement', function () {
    $admin = makeAgent(makeOrganization(), ['is_super_admin' => true]);

    $this->actingAs($admin)
        ->patch('/admin/settings', [
            'wave' => '+225 07 00 00 00 00',
            'bank_name' => 'Ecobank',
            'instructions' => 'Indiquez votre référence.',
        ])
        ->assertRedirect();

    expect(PlatformSetting::paymentDetails()['wave'])
        ->toBe('+225 07 00 00 00 00');
});

it('montre les coordonnées au client dans son espace abonnement', function () {
    PlatformSetting::put('payment_details', [
        'wave' => '+225 07 11 22 33 44',
        'orange_money' => null,
    ]);

    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $this->actingAs($owner)
        ->get('/subscription')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->where(
                'payment_details.wave',
                '+225 07 11 22 33 44'
            )
        );
});

/*
|--------------------------------------------------------------------------
| Identité visuelle
|--------------------------------------------------------------------------
*/

it('enregistre le logo et la couleur d\'une entreprise', function () {
    Storage::fake('public');

    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $this->actingAs($owner)
        ->post('/branding', [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            'brand_color' => '#FF6B00',
            'welcome_message' => 'Bienvenue chez nous',
        ])
        ->assertRedirect();

    $organization->refresh();

    expect($organization->brand_color)->toBe('#FF6B00')
        ->and($organization->logo_path)->not->toBeNull()
        ->and($organization->welcome_message)->toBe('Bienvenue chez nous');

    Storage::disk('public')->assertExists($organization->logo_path);
});

it('refuse une couleur qui n\'est pas hexadécimale', function () {
    /*
     * La valeur est injectée dans le style du widget, sur le site d'un
     * tiers : rien d'autre qu'un code couleur ne doit passer.
     */
    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $this->actingAs($owner)
        ->post('/branding', ['brand_color' => 'red; background:url(x)'])
        ->assertSessionHasErrors('brand_color');
});

it('remplace l\'ancien logo au lieu d\'empiler les fichiers', function () {
    Storage::fake('public');

    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $this->actingAs($owner)->post('/branding', [
        'logo' => UploadedFile::fake()->image('premier.png'),
    ]);

    $premier = $organization->fresh()->logo_path;

    $this->actingAs($owner)->post('/branding', [
        'logo' => UploadedFile::fake()->image('second.png'),
    ]);

    Storage::disk('public')->assertMissing($premier);
    Storage::disk('public')->assertExists($organization->fresh()->logo_path);
});

it('sert l\'identité visuelle au widget', function () {
    $organization = makeOrganization();

    $organization->update([
        'brand_color' => '#0EA5E9',
        'welcome_message' => 'Bonjour, une question ?',
    ]);

    $this->withHeader('X-Widget-Token', $organization->widget_token)
        ->getJson('/api/widget/config')
        ->assertOk()
        ->assertJsonPath('branding.color', '#0EA5E9')
        ->assertJsonPath('branding.welcome', 'Bonjour, une question ?');
});

it('sert une couleur de repli quand rien n\'est configuré', function () {
    $organization = makeOrganization();

    $this->withHeader('X-Widget-Token', $organization->widget_token)
        ->getJson('/api/widget/config')
        ->assertOk()
        ->assertJsonPath('branding.color', '#4f46e5');
});

it('réserve la page apparence au responsable', function () {
    $organization = makeOrganization();

    $agent = makeAgent($organization, ['role' => 'agent']);

    $this->actingAs($agent)->get('/branding')->assertStatus(403);
});
