<?php

use App\Jobs\AnalyzeConversation;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\Contracts\LlmClient;

/*
|--------------------------------------------------------------------------
| Analyse en arrière-plan
|--------------------------------------------------------------------------
|
| Sortie du chemin critique, elle doit continuer d'alimenter la
| supervision : sentiment, intention, résumé.
|
*/

function conversationAvecMessages($organization)
{
    $conversation = Conversation::create([
        'organization_id' => $organization->id,
        'client_id' => makeClient($organization)->id,
        'subject' => 'Livraison',
        'channel' => 'web',
        'status' => 'open',
        'priority' => 'normal',
        'ai_enabled' => true,
        'last_message_at' => now(),
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'sender_type' => 'client',
        'content' => 'Ma commande a trois jours de retard, c\'est inadmissible.',
        'channel' => 'web',
    ]);

    return $conversation;
}

function llmRepondant(string $text): LlmClient
{
    return new class ($text) implements LlmClient {
        public function __construct(private string $text) {}

        public function converse(string $system, array $messages, array $tools = [], array $options = []): array
        {
            return [
                'text' => $this->text,
                'tool_calls' => [],
                'stop_reason' => 'end_turn',
                'raw_content' => [],
                'usage' => ['input_tokens' => 50, 'output_tokens' => 30],
            ];
        }
    };
}

it('enregistre le sentiment et l\'intention', function () {
    $conversation = conversationAvecMessages(makeOrganization());

    (new AnalyzeConversation($conversation->id))->handle(llmRepondant(
        '{"intent":"retard de livraison","sentiment":"angry",'
        . '"summary":"Commande en retard de trois jours.","confidence":0.8}'
    ));

    $conversation = Conversation::withoutGlobalScopes()->find($conversation->id);

    expect($conversation->ai_sentiment)->toBe('angry')
        ->and($conversation->ai_intent)->toBe('retard de livraison')
        ->and($conversation->ai_last_run_at)->not->toBeNull();
});

it('lit un JSON entouré de texte ou de balises de code', function () {
    $conversation = conversationAvecMessages(makeOrganization());

    (new AnalyzeConversation($conversation->id))->handle(llmRepondant(
        "Voici l'analyse :\n```json\n"
        . '{"intent":"retard","sentiment":"negative","summary":"Retard.","confidence":0.7}'
        . "\n```"
    ));

    expect(Conversation::withoutGlobalScopes()->find($conversation->id)->ai_sentiment)
        ->toBe('negative');
});

it('ramène un sentiment inconnu à neutre', function () {
    $conversation = conversationAvecMessages(makeOrganization());

    (new AnalyzeConversation($conversation->id))->handle(llmRepondant(
        '{"intent":"x","sentiment":"furieux","summary":"x","confidence":2}'
    ));

    $conversation = Conversation::withoutGlobalScopes()->find($conversation->id);

    expect($conversation->ai_sentiment)->toBe('neutral')
        // Une confiance hors bornes est ramenée à 1.
        ->and((float) $conversation->ai_confidence)->toBe(1.0);
});

it('ne casse rien si la réponse est illisible', function () {
    $conversation = conversationAvecMessages(makeOrganization());

    (new AnalyzeConversation($conversation->id))->handle(
        llmRepondant('Je ne sais pas.')
    );

    expect(Conversation::withoutGlobalScopes()->find($conversation->id)->ai_sentiment)
        ->toBeNull();
});
