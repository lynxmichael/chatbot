<?php

use App\Models\KnowledgeBase;
use App\Models\KnowledgeImage;
use App\Services\AI\Autopilot\AutopilotPolicy;
use App\Services\AI\Tools\SearchKnowledgeTool;
use App\Services\AI\Tools\SendImagesTool;
use App\Services\AI\Tools\ToolContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Photos dans la base de connaissances
|--------------------------------------------------------------------------
|
| Le risque à écarter : que l'assistant envoie une photo hors sujet, ou
| celle d'une autre entreprise. Il ne peut employer que les
| identifiants retournés par une recherche.
|
*/

function imageContext($organization, string $channel = 'widget'): ToolContext
{
    return new ToolContext(
        organization: $organization,
        policy: AutopilotPolicy::forOrganization($organization),
        conversation: null,
        client: null,
        message: null,
        channel: $channel,
    );
}

function makeEntryWithImage($organization, string $title, string $caption)
{
    $entry = KnowledgeBase::create([
        'organization_id' => $organization->id,
        'title' => $title,
        'content' => 'Description de ' . $title . '.',
        'is_active' => true,
    ]);

    $image = KnowledgeImage::create([
        'organization_id' => $organization->id,
        'knowledge_base_id' => $entry->id,
        'path' => 'knowledge/test/' . uniqid() . '.jpg',
        'caption' => $caption,
    ]);

    return [$entry, $image];
}

it('annonce les photos disponibles lors d\'une recherche', function () {
    $organization = makeOrganization();

    [, $image] = makeEntryWithImage(
        $organization,
        'Chambre Deluxe vue mer',
        'Chambre Deluxe, lit king size'
    );

    $result = app(SearchKnowledgeTool::class)->handle(
        ['query' => 'chambre deluxe'],
        imageContext($organization)
    );

    expect($result['found'])->toBeTrue()
        ->and($result['results'][0]['images'][0]['id'])->toBe($image->id)
        ->and($result['results'][0]['images'][0]['caption'])
        ->toBe('Chambre Deluxe, lit king size');
});

it('envoie les photos demandées', function () {
    $organization = makeOrganization();

    [, $image] = makeEntryWithImage($organization, 'Plat du jour', 'Attiéké poisson');

    $context = imageContext($organization);

    $result = app(SendImagesTool::class)->handle(
        ['image_ids' => [$image->id]],
        $context
    );

    expect($result['sent'])->toBeTrue()
        ->and($context->effects['attachments'])->toHaveCount(1)
        ->and($context->effects['attachments'][0]['caption'])
        ->toBe('Attiéké poisson');
});

it('refuse la photo d\'une autre entreprise', function () {
    $mienne = makeOrganization();

    $autre = makeOrganization();

    [, $image] = makeEntryWithImage($autre, 'Suite présidentielle', 'Suite');

    $result = app(SendImagesTool::class)->handle(
        ['image_ids' => [$image->id]],
        imageContext($mienne)
    );

    expect($result['sent'])->toBeFalse();
});

it('refuse la photo d\'une fiche désactivée', function () {
    /*
     * Cas réel : une offre terminée. La fiche est désactivée, ses
     * photos ne doivent plus circuler.
     */
    $organization = makeOrganization();

    [$entry, $image] = makeEntryWithImage($organization, 'Menu de Noël', 'Menu');

    $entry->update(['is_active' => false]);

    $result = app(SendImagesTool::class)->handle(
        ['image_ids' => [$image->id]],
        imageContext($organization)
    );

    expect($result['sent'])->toBeFalse();
});

it('n\'envoie jamais de photo au téléphone', function () {
    $organization = makeOrganization();

    [, $image] = makeEntryWithImage($organization, 'Chambre', 'Chambre');

    $result = app(SendImagesTool::class)->handle(
        ['image_ids' => [$image->id]],
        imageContext($organization, 'phone')
    );

    expect($result['sent'])->toBeFalse()
        ->and($result['message'])->toContain('téléphone');
});

it('limite le nombre de photos par message', function () {
    $organization = makeOrganization();

    $ids = collect(range(1, 7))
        ->map(function ($index) use ($organization) {
            [, $image] = makeEntryWithImage(
                $organization,
                'Chambre ' . $index,
                'Vue ' . $index
            );

            return $image->id;
        })
        ->all();

    $context = imageContext($organization);

    app(SendImagesTool::class)->handle(['image_ids' => $ids], $context);

    expect($context->effects['attachments'])->toHaveCount(4);
});

it('téléverse des photos depuis l\'interface', function () {
    Storage::fake('public');

    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $entry = KnowledgeBase::create([
        'organization_id' => $organization->id,
        'title' => 'Nos chambres',
        'content' => 'Trois catégories disponibles.',
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->post("/knowledge/{$entry->id}/images", [
            'images' => [UploadedFile::fake()->image('chambre.jpg')],
            'captions' => ['Chambre standard'],
        ])
        ->assertRedirect();

    $image = KnowledgeImage::first();

    expect($image->caption)->toBe('Chambre standard');

    Storage::disk('public')->assertExists($image->path);
});

it('supprime le fichier avec la photo', function () {
    Storage::fake('public');

    $organization = makeOrganization();

    $owner = makeAgent($organization, ['role' => 'owner']);

    $entry = KnowledgeBase::create([
        'organization_id' => $organization->id,
        'title' => 'Nos plats',
        'content' => 'Carte du jour.',
        'is_active' => true,
    ]);

    $this->actingAs($owner)->post("/knowledge/{$entry->id}/images", [
        'images' => [UploadedFile::fake()->image('plat.jpg')],
    ]);

    $image = KnowledgeImage::first();

    $path = $image->path;

    $this->actingAs($owner)
        ->delete("/knowledge/images/{$image->id}")
        ->assertRedirect();

    Storage::disk('public')->assertMissing($path);

    expect(KnowledgeImage::count())->toBe(0);
});

it('empêche de toucher aux photos d\'une autre entreprise', function () {
    $mienne = makeOrganization();

    $autre = makeOrganization();

    $owner = makeAgent($mienne, ['role' => 'owner']);

    [, $image] = makeEntryWithImage($autre, 'Privé', 'Privé');

    /*
     * 404 et non 403 : le cloisonnement opère dès la résolution du
     * modèle, donc la ressource n'existe pas du point de vue de cet
     * utilisateur. C'est préférable à un 403, qui confirmerait
     * l'existence d'une ressource portant cet identifiant ailleurs.
     */
    $this->actingAs($owner)
        ->delete("/knowledge/images/{$image->id}")
        ->assertStatus(404);

    /*
     * Le comptage sort du cloisonnement : sous la session du
     * demandeur, cette photo n'existe pas.
     */
    expect(KnowledgeImage::acrossOrganizations()->count())->toBe(1);
});
