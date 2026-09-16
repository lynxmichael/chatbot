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
    ): array {
        $apiKey = config('services.anthropic.api_key');
        $model = config('services.anthropic.model');

        if (!$apiKey) {
            throw new RuntimeException(
                "ANTHROPIC_API_KEY n'est pas configuree."
            );
        }

        $organizationId = $context['organization_id'] ?? null;

        if (!$organizationId) {
            throw new RuntimeException(
                "L'identifiant de l'organisation est manquant."
            );
        }

        /*
         * Chargement de la base de connaissances.
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
         * Instructions données à Claude.
         */
        $systemPrompt = <<<PROMPT
Tu es l'assistant du service client.

Tu dois répondre uniquement à partir des informations disponibles
dans la base de connaissances ci-dessous et du contexte de la conversation.

REGLES :

1. Réponds de manière professionnelle, claire et concise.

2. Ne fabrique jamais une information.

3. Si l'information demandée est présente dans la base de connaissances,
   utilise-la directement.

4. Si l'information n'est pas disponible, indique clairement que tu ne
   disposes pas de cette information.

5. Si le client demande explicitement à parler à un agent humain,
   considère que la conversation doit être transférée à un humain.

6. Si la demande nécessite une intervention humaine, considère que la
   conversation doit être transférée à un humain.

7. Les problèmes suivants doivent normalement être transférés à un humain :
   - réclamation ;
   - problème de paiement ;
   - problème de commande ;
   - problème de compte ;
   - demande nécessitant des données personnelles ;
   - demande nécessitant une vérification manuelle.

8. Si le client insiste après une réponse insuffisante, considère également
   qu'un transfert humain est nécessaire.

9. Ne prétends jamais avoir effectué une action que tu n'as pas réellement
   effectuée.

10. Ne mentionne jamais au client les termes techniques suivants :
    "Knowledge Base", "base de données", "API", "prompt", "JSON",
    "modèle IA" ou "système interne".

11. Réponds en français sauf si le client utilise clairement une autre langue.

IMPORTANT :

Tu dois retourner UNIQUEMENT un objet JSON valide avec exactement deux champs :

{
  "transfer_to_human": true ou false,
  "response": "ta réponse au client"
}

Ne mets aucun texte avant ou après le JSON.

BASE DE CONNAISSANCES :
{$knowledgeContext}
PROMPT;

        /*
         * Historique de la conversation.
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
                'role' => $role === 'assistant'
                    ? 'assistant'
                    : 'user',
                'content' => $content,
            ];
        }

        /*
         * On s'assure que le dernier message est bien
         * celui actuellement envoyé à Claude.
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
         * Appel de l'API Anthropic.
         */
        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->timeout(60)
            ->post(
                'https://api.anthropic.com/v1/messages',
                [
                    'model' => $model,
                    'max_tokens' => 1024,
                    'system' => $systemPrompt,
                    'messages' => $messages,
                ]
            );

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

        /*
         * Nettoyage éventuel des blocs Markdown.
         */
        $text = trim($text);

        if (str_starts_with($text, '```json')) {
            $text = preg_replace(
                '/^```json\s*/i',
                '',
                $text
            );

            $text = preg_replace(
                '/\s*```$/',
                '',
                $text
            );

            $text = trim($text);
        }

        /*
         * Conversion du JSON retourné par Claude.
         */
        $result = json_decode(
            $text,
            true
        );

        if (
            !is_array($result) ||
            !array_key_exists('transfer_to_human', $result) ||
            !array_key_exists('response', $result)
        ) {
            throw new RuntimeException(
                'La reponse de Claude n\'est pas un JSON valide.'
            );
        }

        /*
         * Vérification du signal de transfert.
         */
        if (!is_bool($result['transfer_to_human'])) {
            throw new RuntimeException(
                'Le champ transfer_to_human est invalide.'
            );
        }

        /*
         * Vérification de la réponse.
         */
        if (
            !is_string($result['response']) ||
            !trim($result['response'])
        ) {
            throw new RuntimeException(
                'Le champ response de Claude est vide.'
            );
        }

        /*
         * Retourne la réponse et la décision de transfert.
         */
        return [
            'response' => trim($result['response']),
            'transfer_to_human' => $result['transfer_to_human'],
        ];
    }
}
