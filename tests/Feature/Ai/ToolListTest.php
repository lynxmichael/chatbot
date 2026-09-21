<?php

use App\Services\AI\Autopilot\AutopilotPolicy;

/*
|--------------------------------------------------------------------------
| Liste des outils : formule et exclusions
|--------------------------------------------------------------------------
|
| Le défaut corrigé, tel qu'il s'est présenté en réalité : une
| entreprise en formule Business, qui inclut l'envoi de photos, dont
| l'assistant répondait pourtant « je ne peux pas afficher d'images ».
|
| Sa page Autopilot avait été enregistrée avant que l'outil existe. La
| liste figée l'emportait sur la formule.
|
*/

it('donne à une entreprise Business l\'envoi de photos', function () {
    $organization = makeOrganization();

    $organization->update(['ai_settings' => ['plan' => 'business']]);

    expect(AutopilotPolicy::forOrganization($organization)->allowedActions())
        ->toContain('send_images');
});

it('fait arriver un nouvel outil même après un enregistrement', function () {
    /*
     * Le cas réel : la page Autopilot est enregistrée, puis la
     * formule change. L'outil de la nouvelle formule doit apparaître.
     */
    $organization = makeOrganization();

    $organization->update(['ai_settings' => ['plan' => 'free']]);

    $owner = makeAgent($organization, ['role' => 'owner']);

    $this->actingAs($owner)->patch('/autopilot', [
        'level' => 'assist',
        'confidence_threshold' => 0.6,
        'auto_close_after_hours' => 72,
        'escalate_on_negative_sentiment' => true,
        'allowed_actions' => AutopilotPolicy::forOrganization($organization)
            ->allowedActions(),
    ])->assertRedirect();

    app(\App\Services\Billing\Billing::class)
        ->applyPlan($organization->fresh(), 'business');

    expect(
        AutopilotPolicy::forOrganization($organization->fresh())->allowedActions()
    )->toContain('send_images');
});

it('respecte un outil volontairement coupé', function () {
    $organization = makeOrganization();

    $organization->update(['ai_settings' => ['plan' => 'business']]);

    $owner = makeAgent($organization, ['role' => 'owner']);

    $tout = AutopilotPolicy::forOrganization($organization)->allowedActions();

    // Tout sauf l'envoi de photos.
    $this->actingAs($owner)->patch('/autopilot', [
        'level' => 'auto',
        'confidence_threshold' => 0.6,
        'auto_close_after_hours' => 72,
        'escalate_on_negative_sentiment' => true,
        'allowed_actions' => array_values(array_diff($tout, ['send_images'])),
    ])->assertRedirect();

    $policy = AutopilotPolicy::forOrganization($organization->fresh());

    expect($policy->allowedActions())->not->toContain('send_images')
        ->and($policy->disabledActions())->toBe(['send_images']);
});

it('ne laisse pas activer un outil hors formule en trichant', function () {
    /*
     * Le formulaire est contrôlable par le navigateur. Cocher un outil
     * que la formule n'inclut pas ne doit rien donner.
     */
    $organization = makeOrganization();

    $organization->update(['ai_settings' => ['plan' => 'free']]);

    $owner = makeAgent($organization, ['role' => 'owner']);

    $this->actingAs($owner)->patch('/autopilot', [
        'level' => 'auto',
        'confidence_threshold' => 0.6,
        'auto_close_after_hours' => 72,
        'escalate_on_negative_sentiment' => true,
        'allowed_actions' => ['search_knowledge', 'send_images', 'get_order_status'],
    ])->assertRedirect();

    $actions = AutopilotPolicy::forOrganization($organization->fresh())
        ->allowedActions();

    expect($actions)->not->toContain('send_images')
        ->and($actions)->not->toContain('get_order_status');
});

it('convertit une liste figée sans perdre les choix réels', function () {
    /*
     * La migration : ce qui avait été décoché reste coupé, ce qui
     * n'existait pas encore arrive actif.
     */
    $organization = makeOrganization();

    \Illuminate\Support\Facades\DB::table('organizations')
        ->where('id', $organization->id)
        ->update([
            'ai_settings' => json_encode([
                'plan' => 'business',
                // Figée avant l'envoi de photos, suivi de commande décoché.
                'allowed_actions' => [
                    'search_knowledge',
                    'get_client_profile',
                    'get_ticket_status',
                    'record_insights',
                    'create_ticket',
                    'update_ticket',
                    'schedule_follow_up',
                    'escalate_to_human',
                ],
            ]),
        ]);

    $migration = require database_path(
        'migrations/2026_09_20_100000_convert_frozen_tool_lists.php'
    );

    $migration->up();

    $actions = AutopilotPolicy::forOrganization($organization->fresh())
        ->allowedActions();

    expect($actions)->toContain('send_images')
        ->and($actions)->not->toContain('get_order_status');
});

it('signale les outils hors formule dans la page', function () {
    $organization = makeOrganization();

    $organization->update(['ai_settings' => ['plan' => 'free']]);

    $owner = makeAgent($organization, ['role' => 'owner']);

    $this->actingAs($owner)
        ->get('/autopilot')
        ->assertOk()
        ->assertInertia(function ($page) {
            $outils = collect($page->toArray()['props']['tools'])
                ->keyBy('name');

            expect($outils['send_images']['included'])->toBeFalse()
                ->and($outils['search_knowledge']['included'])->toBeTrue();
        });
});

/*
|--------------------------------------------------------------------------
| Consigne sur les photos
|--------------------------------------------------------------------------
*/

function promptFor($organization, string $channel = 'web'): string
{
    $policy = AutopilotPolicy::forOrganization($organization);

    $context = new \App\Services\AI\Tools\ToolContext(
        organization: $organization,
        policy: $policy,
        channel: $channel,
    );

    $tools = app(\App\Services\AI\Tools\ToolRegistry::class)
        ->enabledFor($policy->allowedActions());

    return app(\App\Services\AI\PromptBuilder::class)->build($context, $tools);
}

it('interdit à l\'assistant de prétendre ne pas pouvoir montrer de photos', function () {
    /*
     * Le second défaut de la capture : l'assistant avait inventé une
     * limite technique — « impossible d'afficher des images depuis
     * cette fenêtre ».
     */
    $organization = makeOrganization();

    $organization->update(['ai_settings' => ['plan' => 'business']]);

    $prompt = promptFor($organization);

    expect($prompt)->toContain('# PHOTOS')
        ->and($prompt)->toContain("N'affirme JAMAIS que tu ne peux pas afficher");
});

it('ne parle pas de photos à une entreprise qui n\'y a pas droit', function () {
    $organization = makeOrganization();

    $organization->update(['ai_settings' => ['plan' => 'free']]);

    expect(promptFor($organization))->not->toContain('# PHOTOS');
});

it('ne parle pas de photos au téléphone', function () {
    $organization = makeOrganization();

    $organization->update(['ai_settings' => ['plan' => 'business']]);

    expect(promptFor($organization, 'phone'))->not->toContain('# PHOTOS');
});
