<?php

namespace App\Services\AI\Contracts;

interface LlmClient
{
    /**
     * Envoie une conversation au modèle et retourne sa réponse normalisée.
     *
     * $messages : [
     *     ['role' => 'user', 'content' => '...'],
     *     ['role' => 'assistant', 'content' => [...blocs...]],
     * ]
     *
     * $tools : [
     *     [
     *         'name' => 'search_knowledge',
     *         'description' => '...',
     *         'input_schema' => [...],
     *     ],
     * ]
     *
     * Retour normalisé :
     *
     * [
     *     'text' => 'texte visible par le client',
     *     'tool_calls' => [
     *         ['id' => '...', 'name' => '...', 'input' => [...]],
     *     ],
     *     'stop_reason' => 'tool_use' | 'end_turn' | ...,
     *     'raw_content' => [...],   // blocs bruts, à renvoyer tels quels
     *     'usage' => ['input_tokens' => 0, 'output_tokens' => 0],
     * ]
     */
    public function converse(
        string $system,
        array $messages,
        array $tools = [],
        array $options = []
    ): array;
}
