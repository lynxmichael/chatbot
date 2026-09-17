<?php

use App\Models\Organization;
use App\Services\AI\Autopilot\AutopilotPolicy;
use App\Services\AI\Tools\ToolRegistry;

/*
|--------------------------------------------------------------------------
| Politique Autopilot
|--------------------------------------------------------------------------
|
| C'est le garde-fou du produit : il décide de ce que l'IA peut faire
| sans supervision humaine. Une erreur ici signifie une action commise
| au nom d'une entreprise sans son accord.
|
*/

function policyFor(array $settings): AutopilotPolicy
{
    $organization = new Organization(['name' => 'Test']);

    $organization->ai_settings = $settings;

    return AutopilotPolicy::forOrganization($organization);
}

function decisionsFor(AutopilotPolicy $policy): array
{
    $registry = app(ToolRegistry::class);

    $result = [];

    foreach ($registry->enabledFor($policy->allowedActions()) as $tool) {
        $result[$tool->name()] = $policy->decide($tool);
    }

    return $result;
}

it('bloque tout en mode désactivé', function () {
    $decisions = decisionsFor(policyFor(['level' => 'off']));

    expect(array_unique(array_values($decisions)))
        ->toBe([AutopilotPolicy::DENY]);
});

it('autorise la lecture mais interdit l\'écriture en mode suggestion', function () {
    $decisions = decisionsFor(policyFor(['level' => 'suggest']));

    expect($decisions['search_knowledge'])->toBe(AutopilotPolicy::EXECUTE)
        ->and($decisions['get_client_profile'])->toBe(AutopilotPolicy::EXECUTE)
        ->and($decisions['create_ticket'])->toBe(AutopilotPolicy::DENY)
        ->and($decisions['schedule_follow_up'])->toBe(AutopilotPolicy::DENY);
});

it('met les écritures en validation en mode assisté', function () {
    $decisions = decisionsFor(policyFor(['level' => 'assist']));

    expect($decisions['create_ticket'])->toBe(AutopilotPolicy::APPROVE)
        ->and($decisions['update_ticket'])->toBe(AutopilotPolicy::APPROVE)
        ->and($decisions['schedule_follow_up'])->toBe(AutopilotPolicy::APPROVE);
});

it('laisse toujours passer le transfert vers un humain', function () {
    /*
     * Bloquer une escalade en attendant qu'un responsable clique serait
     * l'inverse du but recherché : le client resterait sans réponse.
     */
    foreach (['assist', 'auto'] as $level) {
        $decisions = decisionsFor(policyFor(['level' => $level]));

        expect($decisions['escalate_to_human'])
            ->toBe(AutopilotPolicy::EXECUTE, "niveau {$level}");
    }
});

it('exécute tout en mode autonome', function () {
    $decisions = decisionsFor(policyFor(['level' => 'auto']));

    expect(array_unique(array_values($decisions)))
        ->toBe([AutopilotPolicy::EXECUTE]);
});

it('respecte la liste blanche quel que soit le niveau', function () {
    $policy = policyFor([
        'level' => 'auto',
        'allowed_actions' => ['search_knowledge'],
    ]);

    $registry = app(ToolRegistry::class);

    expect($policy->decide($registry->get('search_knowledge')))
        ->toBe(AutopilotPolicy::EXECUTE)
        ->and($policy->decide($registry->get('create_ticket')))
        ->toBe(AutopilotPolicy::DENY);
});

it('retombe sur la configuration par défaut sans réglage', function () {
    $policy = AutopilotPolicy::forOrganization(null);

    expect($policy->level())->toBe(config('ai.autopilot.level'));
});
