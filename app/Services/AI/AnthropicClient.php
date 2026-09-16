<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\LlmClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnthropicClient implements LlmClient
{
    /**
     * Appelle l'API Messages d'Anthropic.
     */
    public function converse(
        string $system,
        array $messages,
        array $tools = [],
        array $options = []
    ): array {
        $apiKey = config('services.anthropic.api_key');

        if (!$apiKey) {
            throw new RuntimeException(
                "ANTHROPIC_API_KEY n'est pas configurée."
            );
        }

        $payload = [
            'model' => $options['model']
                ?? config('ai.model'),

            'max_tokens' => (int) ($options['max_tokens']
                ?? config('ai.max_tokens')),

            'temperature' => (float) ($options['temperature']
                ?? config('ai.temperature')),

            'system' => $system,

            'messages' => $messages,
        ];

        if (!empty($tools)) {
            $payload['tools'] = $tools;

            /*
             * « auto » laisse le modèle décider :
             * répondre directement ou utiliser un outil.
             */
            $payload['tool_choice'] = [
                'type' => $options['tool_choice'] ?? 'auto',
            ];
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->timeout((int) config('ai.timeout'))
            ->retry(2, 500, throw: false)
            ->post(
                'https://api.anthropic.com/v1/messages',
                $payload
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Erreur API Anthropic ('
                . $response->status()
                . ') : '
                . mb_substr($response->body(), 0, 1000)
            );
        }

        $body = $response->json();

        $content = $body['content'] ?? [];

        if (!is_array($content)) {
            throw new RuntimeException(
                'Réponse Anthropic inattendue.'
            );
        }

        /*
         * Séparation du texte et des appels d'outils.
         */
        $text = '';

        $toolCalls = [];

        foreach ($content as $block) {
            $type = $block['type'] ?? null;

            if ($type === 'text') {
                $text .= $block['text'] ?? '';

                continue;
            }

            if ($type === 'tool_use') {
                $toolCalls[] = [
                    'id' => $block['id'] ?? '',
                    'name' => $block['name'] ?? '',
                    'input' => is_array($block['input'] ?? null)
                        ? $block['input']
                        : [],
                ];
            }
        }

        return [
            'text' => trim($text),
            'tool_calls' => $toolCalls,
            'stop_reason' => $body['stop_reason'] ?? null,
            'raw_content' => $content,
            'usage' => [
                'input_tokens' => $body['usage']['input_tokens'] ?? 0,
                'output_tokens' => $body['usage']['output_tokens'] ?? 0,
            ],
        ];
    }
}
