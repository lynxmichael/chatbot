<?php

use App\Models\Conversation;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeImage;
use App\Models\Message;
use App\Services\AI\AgentRunner;
use App\Services\AI\Contracts\LlmClient;

/*
|--------------------------------------------------------------------------
| Rapidité
|--------------------------------------------------------------------------
|
| Chaque appel au modèle coûte plusieurs secondes. Le nombre d'appels
| par réponse est donc LE chiffre qui fait le temps d'attente du client.
|
| Avant : trois à quatre appels par message (recherche, analyse,
| réponse, et photos le cas échéant) — les 26 secondes mesurées en réel.
|
*/

/**
 * Modèle simulé, qui se comporte comme le vrai : s'il voit les fiches
 * dans son contexte, il répond directement ; sinon il cherche.
 */
class CountingLlm implements LlmClient
{
    public int $calls = 0;

    public array $seenTools = [];

    public array $systems = [];

    public function converse(
        string $system,
        array $messages,
        array $tools = [],
        array $options = []
    ): array {
        $this->calls++;

        $this->systems[] = $system;

        $this->seenTools = array_column($tools, 'name');

        $last = end($messages);

        $content = is_string($last['content'] ?? null)
            ? $last['content']
            : json_encode($last['content']);

        $hasKnowledge = str_contains($content, 'INFORMATIONS TROUVÉES');

        /*
         * Photos annoncées dans le contexte : le modèle les envoie
         * directement, dans son premier tour.
         */
        if ($hasKnowledge && preg_match('/#(\d+)/', $content, $match) && $this->calls === 1) {
            return $this->tool('send_images', ['image_ids' => [(int) $match[1]]]);
        }

        if ($hasKnowledge || $this->calls > 1) {
            return [
                'text' => 'Voici la réponse.',
                'tool_calls' => [],
                'stop_reason' => 'end_turn',
                'raw_content' => [['type' => 'text', 'text' => 'Voici la réponse.']],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 10],
            ];
        }

        return $this->tool('search_knowledge', ['query' => 'horaires']);
    }

    private function tool(string $name, array $input): array
    {
        $block = [
            'type' => 'tool_use',
            'id' => 'call_' . $this->calls,
            'name' => $name,
            'input' => $input,
        ];

        return [
            'text' => '',
            'tool_calls' => [['id' => $block['id'], 'name' => $name, 'input' => $input]],
            'stop_reason' => 'tool_use',
            'raw_content' => [$block],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 10],
        ];
    }
}

function scenario(string $question, bool $withPhoto = false): CountingLlm
{
    $organization = makeOrganization();

    $organization->update(['ai_settings' => ['plan' => 'business']]);

    $entry = KnowledgeBase::create([
        'organization_id' => $organization->id,
        'title' => $withPhoto ? 'Chambres vue sur mer' : 'Horaires d\'ouverture',
        'content' => $withPhoto
            ? 'Chambres Deluxe avec balcon.'
            : 'Ouvert du lundi au samedi de 8h à 18h.',
        'is_active' => true,
    ]);

    if ($withPhoto) {
        KnowledgeImage::create([
            'organization_id' => $organization->id,
            'knowledge_base_id' => $entry->id,
            'path' => 'knowledge/1/vue.jpg',
            'caption' => 'Vue sur mer',
        ]);
    }

    $conversation = Conversation::create([
        'organization_id' => $organization->id,
        'client_id' => makeClient($organization)->id,
        'subject' => 'Question',
        'channel' => 'web',
        'status' => 'open',
        'priority' => 'normal',
        'ai_enabled' => true,
        'last_message_at' => now(),
    ]);

    $message = Message::create([
        'conversation_id' => $conversation->id,
        'sender_type' => 'client',
        'content' => $question,
        'channel' => 'web',
    ]);

    $llm = new CountingLlm();

    app()->instance(LlmClient::class, $llm);

    app(AgentRunner::class)->forConversation($conversation, $message);

    return $llm;
}

it('répond à une question courante en un seul appel', function () {
    /*
     * Les fiches sont transmises d'emblée : plus besoin d'un premier
     * appel pour chercher, puis d'un second pour répondre.
     */
    $llm = scenario('Quels sont vos horaires d\'ouverture ?');

    expect($llm->calls)->toBe(1);
});

it('envoie des photos en deux appels au lieu de quatre', function () {
    /*
     * Avant : recherche, photos, analyse, réponse.
     * Maintenant : photos, réponse.
     */
    $llm = scenario('Je voudrais voir les chambres vue sur mer', withPhoto: true);

    expect($llm->calls)->toBe(2);
});

it('ne propose plus l\'analyse au modèle pendant qu\'il répond', function () {
    $llm = scenario('Quels sont vos horaires d\'ouverture ?');

    expect($llm->seenTools)->not->toContain('record_insights');
});

it('ne demande plus au modèle d\'analyser avant de répondre', function () {
    $llm = scenario('Quels sont vos horaires d\'ouverture ?');

    expect($llm->systems[0])->not->toContain('Appelle record_insights');
});

it('garde des instructions identiques d\'un appel à l\'autre', function () {
    /*
     * Condition du cache : si les instructions varient entre deux
     * appels d'un même échange, rien n'est réutilisé.
     */
    $llm = scenario('Je voudrais voir les chambres vue sur mer', withPhoto: true);

    expect($llm->systems)->toHaveCount(2)
        ->and($llm->systems[0])->toBe($llm->systems[1]);
});

it('garde la recherche à disposition du modèle s\'il doit reformuler', function () {
    $llm = scenario('Quels sont vos horaires d\'ouverture ?');

    expect($llm->seenTools)->toContain('search_knowledge');
});
