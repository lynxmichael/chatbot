<?php

namespace App\Services\AI;

use App\Models\KnowledgeBase;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnthropicProvider implements AiProvider
{
    public function generateResponse(
        string $message,
        array $context = []
    ): string {
        $apiKey = config('services.anthropic.api_key');
        $model = config('services.anthropic.model');

        if (!$apiKey) {
            throw new RuntimeException(
                "ANTHROPIC_API_KEY n'est pas configuree."
            );
        }

        /*
         * Recuperation de l'organisation
         */
        $organizationId = $context['organization_id'] ?? null;

        if (!$organizationId) {
            throw new RuntimeException(
                "L'identifiant de l'organisation est manquant."
            );
        }

        /*
         * Recuperation de la Knowledge Base
         */
        $knowledgeBases = KnowledgeBase::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $knowledgeContext = '';

        foreach ($knowledgeBases as $knowledge) {
            $knowledgeContext .= "\n\n";
            $knowledgeContext .= "Titre : " . $knowledge->title . "\n";
            $knowledgeContext .= "Categorie : " . $knowledge->category . "\n";
            $knowledgeContext .= "Informations : " . $knowledge->content;
        }

        /*
         * Construction du system prompt
         */
        $systemPrompt = <<<PROMPT
Tu es l'assistant du service client.

Regles importantes :
- Reponds de maniere professionnelle, claire et concise.
- Utilise prioritairement les informations de la base de connaissances ci-dessous.
- Ne fabrique jamais une information.
- Si une information n'est pas presente dans la base de connaissances, dis clairement que tu ne disposes pas de cette information.
- Si la demande necessite une intervention humaine, recommande un agent humain.
- Ne mentionne pas l'existence technique de la "Knowledge Base" au client.
- Reponds en francais sauf si le client utilise une autre langue.

BASE DE CONNAISSANCES :
{$knowledgeContext}
PROMPT;

        /*
         * Historique de la conversation
         */
        $history = $context['history'] ?? [];

        $messages = [];

        foreach ($history as $item) {
            $role = $item['role'] ?? 'user';
            $content = $item['content'] ?? '';

            if (!$content) {
                continue;
            }

            $messages[] = [
                'role' => $role === 'assistant' ? 'assistant' : 'user',
                'content' => $content,
            ];
        }

        /*
         * Si l'historique ne contient pas le message actuel,
         * on l'ajoute.
         */
        $lastMessage = end($messages);

        if (
            !$lastMessage ||
            ($lastMessage['role'] ?? null) !== 'user' ||
            ($lastMessage['content'] ?? null) !== $message
        ) {
            $messages[] = [
                'role' => 'user',
                'content' => $message,
            ];
        }

        /*
         * Appel API Anthropic
         */
        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->timeout(60)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 1024,
                'system' => $systemPrompt,
                'messages' => $messages,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Erreur API Anthropic : ' . $response->body()
            );
        }

        $text = $response->json('content.0.text');

        if (!$text) {
            throw new RuntimeException(
                'La reponse de Claude est vide.'
            );
        }

        return $text;
    }
}
