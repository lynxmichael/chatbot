<?php

use App\Models\KnowledgeBase;
use App\Models\KnowledgeImage;
use App\Services\AI\Autopilot\AutopilotPolicy;
use App\Services\AI\Support\KnowledgeSearch;
use App\Services\AI\Tools\SearchKnowledgeTool;
use App\Services\AI\Tools\ToolContext;

/*
|--------------------------------------------------------------------------
| Recherche dans la base de connaissances
|--------------------------------------------------------------------------
|
| Reproduit le cas réel remonté par le diagnostic : « vue sur mer »
| trouvait « Responsable du service client » et ratait la fiche des
| chambres, qui portait pourtant une photo légendée « vue sur mer ».
|
*/

function fiche($organization, string $title, string $content, array $captions = [])
{
    $entry = KnowledgeBase::create([
        'organization_id' => $organization->id,
        'title' => $title,
        'content' => $content,
        'is_active' => true,
    ]);

    foreach ($captions as $caption) {
        KnowledgeImage::create([
            'organization_id' => $organization->id,
            'knowledge_base_id' => $entry->id,
            'path' => 'knowledge/test/' . uniqid() . '.jpg',
            'caption' => $caption,
        ]);
    }

    return $entry;
}

function chercher($organization, string $query): array
{
    return app(SearchKnowledgeTool::class)->handle(
        ['query' => $query],
        new ToolContext(
            organization: $organization,
            policy: AutopilotPolicy::forOrganization($organization),
            channel: 'web',
        )
    );
}

it('trouve la fiche des chambres pour « vue sur mer »', function () {
    $organization = makeOrganization();

    // Les fiches qui remontaient à tort.
    fiche(
        $organization,
        'Services du service client',
        'Nous vous remercions de votre confiance. Notre service commercial '
            . 'est disponible sur rendez-vous. Merci de préciser votre demande.'
    );

    fiche(
        $organization,
        'Responsable du service client',
        'Le responsable commercial traite les réclamations sur demande.'
    );

    // La bonne fiche : le mot « mer » n'apparaît que dans les légendes.
    fiche(
        $organization,
        'proposition des chambre',
        'Nous proposons trois catégories de chambres confortables.',
        ['chambre deluxe', 'vue sur mer', 'chambre aéré']
    );

    $result = chercher($organization, 'vue sur mer');

    expect($result['found'])->toBeTrue()
        ->and($result['results'][0]['title'])->toBe('proposition des chambre')
        ->and($result['results'][0]['images'])->toHaveCount(3);
});

it('ne confond plus « mer » avec « merci » ou « commercial »', function () {
    $organization = makeOrganization();

    fiche(
        $organization,
        'Remerciements',
        'Merci pour votre commande. Notre équipe commerciale vous remercie.'
    );

    $result = chercher($organization, 'mer');

    expect($result['found'])->toBeFalse();
});

it('ignore les mots vides du français', function () {
    $terms = app(KnowledgeSearch::class)->terms(
        'Je voudrais voir une chambre avec vue sur la mer'
    );

    expect($terms->all())->toBe(['chambre', 'vue', 'mer']);
});

it('écarte les mots vides avant de retirer le pluriel', function () {
    /*
     * Dans l'ordre inverse, « vous » devenait « vou » et échappait
     * au filtre.
     */
    $terms = app(KnowledgeSearch::class)->terms('Vous avez des chambres ?');

    expect($terms->all())->toBe(['chambre']);
});

it('rapproche singulier et pluriel', function () {
    $organization = makeOrganization();

    fiche($organization, 'Nos plats', 'Attiéké poisson, alloco, garba.');

    expect(chercher($organization, 'quel plat proposez-vous')['found'])->toBeTrue()
        ->and(chercher($organization, 'vos plats du jour')['found'])->toBeTrue();
});

it('ignore les accents', function () {
    $organization = makeOrganization();

    fiche($organization, 'Chambre aérée', 'Grande fenêtre, climatisation.');

    expect(chercher($organization, 'chambre aeree')['found'])->toBeTrue();
});

it('préfère la fiche qui répond à toute la question', function () {
    $organization = makeOrganization();

    fiche($organization, 'Vue', 'Vue sur le jardin, vue sur la piscine.');

    fiche(
        $organization,
        'Suite océane',
        'Suite avec terrasse privée.',
        ['Vue sur mer depuis la terrasse']
    );

    $result = chercher($organization, 'vue sur mer');

    expect($result['results'][0]['title'])->toBe('Suite océane');
});

it('lit les légendes des photos', function () {
    $organization = makeOrganization();

    fiche(
        $organization,
        'Espace détente',
        'Ouvert tous les jours.',
        ['Piscine à débordement']
    );

    expect(chercher($organization, 'piscine')['found'])->toBeTrue();
});
