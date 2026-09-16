<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAIProvider implements AiProvider
{
    public function generateResponse(
        string $message,
        array $context = []
    ): string {
        $apiKey = config('services.openai.api_key');

        if (!$apiKey) {
            throw new RuntimeException(
                'OPENAI_API_KEY n\'est pas configurÃ©e.'
            );
        }

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/responses', [
                'model' => config(
                    'services.openai.model',
                    'gpt-4.1-mini'
                ),
                'instructions' => <<<'PROMPT'
Tu es l'assistant du service client.

RÃ©ponds de maniÃ¨re professionnelle, claire et concise.
Ne prÃ©tends jamais connaÃ®tre une information qui n'est pas disponible.
Si tu ne peux pas rÃ©pondre correctement, indique qu'un agent humain doit intervenir.
PROMPT,
                'input' => $message,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Erreur API IA : ' . $response->body()
            );
        }

        return $response->json('output.0.content.0.text')
            ?? throw new RuntimeException(
                'La rÃ©ponse de l\'IA est vide.'
            );
    }
}
