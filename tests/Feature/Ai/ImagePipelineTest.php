<?php

use App\Jobs\ProcessIncomingMessage;
use App\Models\Conversation;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeImage;
use App\Models\Message;
use App\Services\AI\Contracts\LlmClient;

/*
|--------------------------------------------------------------------------
| Chaîne complète des images
|--------------------------------------------------------------------------
|
| send_images → effects → AgentResult → metadata du message → API du
| widget → affichage.
|
| Chaque maillon fonctionnait isolément ; ce test vérifie qu'ils sont
| réellement reliés. Une rupture au milieu donnerait une IA qui croit
| avoir envoyé des photos que le client ne voit jamais.
|
*/

/**
 * Modèle simulé : deux tours, un appel d'outil puis la réponse finale.
 */
class FakeLlm implements LlmClient
{
    public function __construct(
        private array $imageIds
    ) {
    }

    private int $turn = 0;

    public function converse(
        string $system,
        array $messages,
        array $tools = [],
        array $options = []
    ): array {
        $this->turn++;

        if ($this->turn === 1) {
            return [
                'text' => '',
                'tool_calls' => [[
                    'id' => 'call_1',
                    'name' => 'send_images',
                    'input' => ['image_ids' => $this->imageIds],
                ]],
                'stop_reason' => 'tool_use',
                'raw_content' => [[
                    'type' => 'tool_use',
                    'id' => 'call_1',
                    'name' => 'send_images',
                    'input' => ['image_ids' => $this->imageIds],
                ]],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ];
        }

        return [
            'text' => 'Voici nos chambres.',
            'tool_calls' => [],
            'stop_reason' => 'end_turn',
            'raw_content' => [['type' => 'text', 'text' => 'Voici nos chambres.']],
            'usage' => ['input_tokens' => 12, 'output_tokens' => 6],
        ];
    }
}

it('achemine les photos de l\'outil jusqu\'au widget', function () {
    $organization = makeOrganization();

    $organization->update(['ai_settings' => ['plan' => 'business']]);

    $client = makeClient($organization);

    $entry = KnowledgeBase::create([
        'organization_id' => $organization->id,
        'title' => 'Nos chambres',
        'content' => 'Trois catégories.',
        'is_active' => true,
    ]);

    $image = KnowledgeImage::create([
        'organization_id' => $organization->id,
        'knowledge_base_id' => $entry->id,
        'path' => 'knowledge/1/chambre.jpg',
        'caption' => 'Chambre Deluxe',
    ]);

    app()->bind(LlmClient::class, fn () => new FakeLlm([$image->id]));

    $conversation = Conversation::create([
        'organization_id' => $organization->id,
        'client_id' => $client->id,
        'subject' => 'Photos',
        'channel' => 'web',
        'status' => 'open',
        'priority' => 'normal',
        'ai_enabled' => true,
        'last_message_at' => now(),
    ]);

    $incoming = Message::create([
        'conversation_id' => $conversation->id,
        'sender_type' => 'client',
        'content' => 'Je peux voir les chambres ?',
        'channel' => 'web',
        'ai_processed' => false,
        'ai_status' => 'pending',
    ]);

    /*
     * Maillon 1 à 3 : l'outil dépose la pièce jointe, elle traverse
     * AgentResult et se retrouve dans les métadonnées du message.
     */
    (new ProcessIncomingMessage($incoming->id))->handle(
        app(\App\Services\AI\AgentRunner::class),
        app(\App\Services\AI\Usage\UsageMeter::class)
    );

    $reply = Message::where('conversation_id', $conversation->id)
        ->where('sender_type', 'ai')
        ->latest('id')
        ->first();

    expect($reply)->not->toBeNull()
        ->and($reply->content)->toBe('Voici nos chambres.')
        ->and($reply->metadata['attachments'])->toHaveCount(1)
        ->and($reply->metadata['attachments'][0]['caption'])
        ->toBe('Chambre Deluxe');

    /*
     * Maillon 4 : l'API du widget les expose, et rien d'autre des
     * métadonnées internes.
     */
    $response = $this->withHeader('X-Widget-Token', $organization->widget_token)
        ->getJson("/api/widget/conversations/{$conversation->id}/messages")
        ->assertOk();

    $aiMessage = collect($response->json('messages'))
        ->firstWhere('sender_type', 'ai');

    expect($aiMessage['attachments'])->toHaveCount(1)
        ->and($aiMessage['attachments'][0]['caption'])->toBe('Chambre Deluxe')
        ->and($aiMessage['attachments'][0]['url'])->toContain('chambre.jpg')
        // Les métadonnées internes ne sortent pas.
        ->and($aiMessage)->not->toHaveKey('metadata');
});

it('ne laisse pas fuiter les métadonnées internes vers le widget', function () {
    $organization = makeOrganization();

    $client = makeClient($organization);

    $conversation = Conversation::create([
        'organization_id' => $organization->id,
        'client_id' => $client->id,
        'subject' => 'Test',
        'channel' => 'web',
        'status' => 'open',
        'priority' => 'normal',
        'ai_enabled' => true,
        'last_message_at' => now(),
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'sender_type' => 'ai',
        'content' => 'Réponse',
        'channel' => 'web',
        'ai_generated' => true,
        'ai_processed' => true,
        'ai_status' => 'completed',
        'metadata' => [
            'usage' => ['input_tokens' => 4200],
            'tools_used' => ['search_knowledge'],
            'confidence' => 0.9,
        ],
    ]);

    $body = $this->withHeader('X-Widget-Token', $organization->widget_token)
        ->getJson("/api/widget/conversations/{$conversation->id}/messages")
        ->assertOk()
        ->json();

    $serialized = json_encode($body);

    expect($serialized)->not->toContain('4200')
        ->and($serialized)->not->toContain('tools_used')
        ->and($serialized)->not->toContain('confidence');
});
