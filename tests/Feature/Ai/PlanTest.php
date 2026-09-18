<?php

use App\Services\AI\Autopilot\AutopilotPolicy;
use App\Services\AI\Tools\ToolRegistry;
use App\Services\AI\Usage\UsageMeter;

/*
|--------------------------------------------------------------------------
| Formules
|--------------------------------------------------------------------------
|
| Trois couches : valeurs par défaut, formule, réglages propres.
| L'ordre de priorité décide de ce que chaque client paie et obtient,
| donc il vaut mieux qu'il soit vérifié.
|
*/

it('applique les plafonds du fichier de configuration en gratuit', function () {
    /*
     * La formule gratuite ne fixe pas ses plafonds : elle prend ceux du
     * .env. C'est ce qui permet d'ouvrir la voix à tout le monde d'une
     * seule variable, pendant une démonstration par exemple.
     */
    $organization = makeOrganization();

    $organization->update(['ai_settings' => ['plan' => 'free']]);

    $meter = app(UsageMeter::class);

    expect($meter->allows($organization, 'voice_calls'))->toBeFalse();

    config(['ai.quota.voice_calls' => -1]);

    expect($meter->allows($organization, 'voice_calls'))->toBeTrue();
});

it('ouvre la voix et lève la limite de messages en formule pro', function () {
    $organization = makeOrganization();

    $organization->update(['ai_settings' => ['plan' => 'pro']]);

    $meter = app(UsageMeter::class);

    expect($meter->allows($organization, 'voice_calls'))->toBeTrue()
        ->and($meter->allows($organization, 'ai_messages'))->toBeTrue()
        ->and($meter->quota($organization)['ai_messages'])->toBe(-1)
        ->and($meter->quota($organization)['voice_calls'])->toBe(300);
});

it('donne moins d\'outils en gratuit qu\'en pro', function () {
    $gratuite = makeOrganization();
    $gratuite->update(['ai_settings' => ['plan' => 'free']]);

    $payante = makeOrganization();
    $payante->update(['ai_settings' => ['plan' => 'pro']]);

    $registry = app(ToolRegistry::class);

    $outils = fn ($organization) => count(
        $registry->enabledFor(
            AutopilotPolicy::forOrganization($organization)->allowedActions()
        )
    );

    expect($outils($gratuite))->toBeLessThan($outils($payante));

    /*
     * Le suivi de commande et les relances sont réservés au payant.
     */
    $policyGratuite = AutopilotPolicy::forOrganization($gratuite);

    expect($policyGratuite->decide($registry->get('get_order_status')))
        ->toBe(AutopilotPolicy::DENY)
        ->and($policyGratuite->decide($registry->get('schedule_follow_up')))
        ->toBe(AutopilotPolicy::DENY);
});

it('passe en mode autonome en formule business', function () {
    $organization = makeOrganization();

    $organization->update(['ai_settings' => ['plan' => 'business']]);

    expect(AutopilotPolicy::forOrganization($organization)->level())
        ->toBe('auto');
});

it('remplace la liste d\'outils au lieu de la compléter', function () {
    /*
     * Le piège d'une fusion récursive : une formule de six outils
     * laisserait passer les trois suivants de la couche précédente.
     */
    $organization = makeOrganization();

    $organization->update(['ai_settings' => ['plan' => 'free']]);

    $actions = AutopilotPolicy::forOrganization($organization)
        ->allowedActions();

    expect($actions)->toHaveCount(6)
        ->and($actions)->not->toContain('get_order_status');
});

it('laisse un réglage propre passer devant la formule', function () {
    $organization = makeOrganization();

    // Un client gratuit à qui on accorde la voix à titre d'essai.
    $organization->update([
        'ai_settings' => [
            'plan' => 'free',
            'quota' => ['voice_calls' => 20],
        ],
    ]);

    $meter = app(UsageMeter::class);

    expect($meter->allows($organization, 'voice_calls'))->toBeTrue()
        ->and($meter->quota($organization)['voice_calls'])->toBe(20)
        // Le reste de la formule gratuite reste appliqué.
        ->and($meter->quota($organization)['ai_messages'])->toBe(100);
});

it('conserve les exceptions quand la formule change', function () {
    $organization = makeOrganization();

    $organization->update([
        'ai_settings' => [
            'plan' => 'free',
            'tone' => 'chaleureux et direct',
        ],
    ]);

    $this->artisan('ai:plan', [
        'organization' => $organization->id,
        'plan' => 'pro',
    ])->assertSuccessful();

    $organization->refresh();

    expect($organization->plan())->toBe('pro')
        ->and($organization->aiSettings()['tone'])
        ->toBe('chaleureux et direct');
});

it('refuse une formule inconnue', function () {
    $organization = makeOrganization();

    $this->artisan('ai:plan', [
        'organization' => $organization->id,
        'plan' => 'formule-inventee',
    ])->assertFailed();

    expect($organization->fresh()->plan())->not->toBe('formule-inventee');
});
