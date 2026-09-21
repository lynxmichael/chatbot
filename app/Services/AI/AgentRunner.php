<?php

namespace App\Services\AI;

use App\Models\AiAction;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\Autopilot\AutopilotPolicy;
use App\Services\AI\Contracts\LlmClient;
use App\Services\AI\Tools\Tool;
use App\Services\AI\Tools\ToolContext;
use App\Services\AI\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Le cerveau.
 *
 * Le modèle ne se contente plus de rédiger une réponse : il peut
 * chercher, vérifier, agir, puis répondre. Cette classe orchestre
 * ce cycle et applique les règles de l'Autopilot à chaque action.
 */
class AgentRunner
{
    public function __construct(
        private readonly LlmClient $llm,
        private readonly ToolRegistry $registry,
        private readonly PromptBuilder $prompts,
    ) {
    }

    /**
     * Traite le dernier message client d'une conversation.
     */
    public function forConversation(
        Conversation $conversation,
        Message $message
    ): AgentResult {
        $organization = $conversation->organization;

        if (!$organization) {
            throw new RuntimeException(
                'Organisation introuvable pour la conversation '
                . $conversation->id
            );
        }

        $context = new ToolContext(
            organization: $organization,
            policy: AutopilotPolicy::forOrganization($organization),
            conversation: $conversation,
            client: $conversation->client,
            message: $message,
            channel: $message->channel ?: $conversation->channel ?: 'widget',
        );

        $messages = $this->historyFor($conversation, $message);

        /*
         * Recherche préalable.
         *
         * Le modèle commençait chaque échange par un appel à
         * search_knowledge, puis attendait le résultat, puis répondait :
         * deux allers-retours au minimum, trois ou quatre avec les
         * photos. Chaque aller-retour coûte plusieurs secondes.
         *
         * La recherche est locale et quasi instantanée. On la fait donc
         * avant d'interroger le modèle, et on lui transmet directement
         * ce qu'elle a trouvé. Pour une question courante, il peut
         * répondre dès le premier appel. L'outil reste disponible s'il
         * a besoin de reformuler.
         */
        $prefetched = $this->prefetchKnowledge($message, $context);

        if ($prefetched !== '') {
            $messages = self::appendUserTurn($messages, $prefetched);
        }

        return $this->run($messages, $context);
    }

    /**
     * Fiches pertinentes, mises en forme pour le modèle.
     */
    private function prefetchKnowledge(Message $message, ToolContext $context): string
    {
        $allowed = $context->policy->allowedActions();

        if (!in_array('search_knowledge', $allowed, true)) {
            return '';
        }

        $query = trim((string) $message->content);

        if (mb_strlen($query) < 3) {
            return '';
        }

        $result = app(\App\Services\AI\Tools\SearchKnowledgeTool::class)
            ->handle(['query' => $query], $context);

        if (!($result['found'] ?? false)) {
            return '';
        }

        $lines = [
            '[INFORMATIONS TROUVÉES DANS LES FICHES — utilise-les pour '
            . 'répondre directement si elles suffisent. Ce bloc n\'est pas '
            . 'écrit par le client.]',
        ];

        foreach ($result['results'] as $entry) {
            $lines[] = '';
            $lines[] = '## ' . $entry['title'];
            $lines[] = $entry['content'];

            /*
             * Les photos sont annoncées avec leurs identifiants : le
             * modèle peut appeler send_images immédiatement, sans
             * passer d'abord par une recherche.
             */
            if (!empty($entry['images'])) {
                $lines[] = 'Photos disponibles : ' . collect($entry['images'])
                    ->map(fn ($image) => '#' . $image['id'] . ' « ' . $image['caption'] . ' »')
                    ->implode(', ');
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Exécute la boucle d'agent sur un historique donné.
     *
     * @param array $messages Historique au format API.
     */
    public function run(array $messages, ToolContext $context): AgentResult
    {
        $policy = $context->policy;

        /*
         * L'analyse de la conversation se fait désormais en arrière-plan
         * (AnalyzeConversation). Proposer encore record_insights au
         * modèle l'inciterait à l'appeler à chaque message — sa
         * description l'y invite — et chaque appel coûte un aller-retour
         * complet pendant que le client attend.
         */
        $tools = array_values(array_filter(
            $this->registry->enabledFor($policy->allowedActions()),
            fn ($tool) => $tool->name() !== 'record_insights'
        ));

        $system = $this->prompts->build($context, $tools);

        $schemas = $this->registry->toSchema($tools);

        /*
         * Au téléphone, le client attend en ligne et l'opérateur coupe
         * la requête au bout de quelques secondes : la boucle est
         * volontairement plus courte.
         */
        $maxSteps = $context->channel === 'phone'
            ? max(1, (int) config('ai.voice.max_steps', 3))
            : max(1, (int) config('ai.max_steps', 6));

        $actions = [];

        $usage = ['input_tokens' => 0, 'output_tokens' => 0];

        $reply = '';

        $steps = 0;

        /*
         * Budget de temps.
         *
         * Le nombre d'étapes ne suffit pas à protéger un appel
         * téléphonique : trois étapes lentes dépassent largement le
         * délai d'attente de l'opérateur, et le client entend un blanc
         * puis une coupure. On borne donc aussi la durée réelle.
         */
        $budget = $context->channel === 'phone'
            ? (float) config('ai.voice.time_budget', 10)
            : (float) config('ai.time_budget', 60);

        $startedAt = microtime(true);

        $outOfTime = false;

        $timings = [];

        for ($step = 0; $step < $maxSteps; $step++) {
            $steps = $step + 1;

            /*
             * Temps écoulé avant cette étape : on n'entame pas un tour
             * supplémentaire qu'on ne pourra pas terminer.
             */
            if (
                $budget > 0
                && $step > 0
                && (microtime(true) - $startedAt) >= $budget
            ) {
                $outOfTime = true;

                Log::info(
                    "Budget de temps épuisé : réponse finale forcée.",
                    [
                        'channel' => $context->channel,
                        'organization_id' => $context->organization->id,
                        'steps' => $step,
                        'elapsed' => round(microtime(true) - $startedAt, 2),
                    ]
                );

                break;
            }

            $callStartedAt = microtime(true);

            $response = $this->llm->converse(
                $system,
                $messages,
                $schemas
            );

            $timings[] = [
                'step' => $steps,
                'seconds' => round(microtime(true) - $callStartedAt, 2),
                'tools' => array_column($response['tool_calls'] ?? [], 'name'),
                'cached_tokens' => $response['usage']['cache_read_input_tokens'] ?? 0,
            ];

            $usage['input_tokens'] += $response['usage']['input_tokens'] ?? 0;

            $usage['output_tokens'] += $response['usage']['output_tokens'] ?? 0;

            $toolCalls = $response['tool_calls'] ?? [];

            /*
             * Le modèle a terminé : sa réponse est destinée au client.
             */
            if (empty($toolCalls)) {
                $reply = $response['text'];

                break;
            }

            /*
             * On réinjecte tel quel le tour du modèle, blocs d'outils compris.
             */
            $messages[] = [
                'role' => 'assistant',
                'content' => $response['raw_content'],
            ];

            $results = [];

            foreach ($toolCalls as $call) {
                [$output, $action] = $this->executeTool($call, $context);

                $actions[] = $action;

                $results[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $call['id'],
                    'content' => json_encode(
                        $output,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ),
                    'is_error' => (bool) ($output['error'] ?? false),
                ];
            }

            $messages[] = [
                'role' => 'user',
                'content' => $results,
            ];
        }

        /*
         * Le modèle n'a pas conclu : étapes épuisées, ou temps écoulé.
         * On lui demande une réponse finale, sans outil cette fois.
         */
        if (!trim($reply)) {
            $reply = $this->forceFinalAnswer(
                $system,
                $messages,
                $usage,
                $outOfTime
            );
        }

        /*
         * Durée de chaque appel au modèle, dans le journal. C'est ce
         * qui permet de savoir où passe le temps plutôt que de le
         * deviner.
         */
        Log::info(
            'Réponse IA produite.',
            [
                'organization_id' => $context->organization->id,
                'channel' => $context->channel,
                'total_seconds' => round(microtime(true) - $startedAt, 2),
                'calls' => count($timings),
                'timings' => $timings,
            ]
        );

        return new AgentResult(
            reply: $this->toPlainText($reply),
            escalated: (bool) ($context->effects['escalated'] ?? false),
            isDraft: $policy->isDraftOnly(),
            effects: $context->effects,
            actions: $actions,
            steps: $steps,
            usage: $usage,
        );
    }

    /**
     * Retire toute mise en forme Markdown de la réponse.
     *
     * La consigne donnée au modèle suffit la plupart du temps, mais
     * « la plupart du temps » ne convient pas pour quelque chose que
     * le client voit. La fenêtre de discussion affiche le texte brut :
     * un astérisque oublié s'affiche tel quel.
     *
     * Le nettoyage est volontairement limité aux marqueurs de mise en
     * forme. On ne touche ni à la ponctuation, ni aux montants, ni aux
     * références de commande.
     */
    private function toPlainText(string $reply): string
    {
        $text = $reply;

        /*
         * Gras et italique : **texte**, __texte__, *texte*, _texte_.
         * Le motif exige du contenu entre les marqueurs, pour ne pas
         * abîmer une expression comme « 3 * 4 ».
         */
        $text = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/su', '$1', $text);
        $text = preg_replace('/__(?=\S)(.+?)(?<=\S)__/su', '$1', $text);
        $text = preg_replace('/(?<![\w*])\*(?=\S)([^*\n]+?)(?<=\S)\*(?![\w*])/u', '$1', $text);
        $text = preg_replace('/(?<![\w_])_(?=\S)([^_\n]+?)(?<=\S)_(?![\w_])/u', '$1', $text);

        /*
         * Code : `texte` et blocs ```.
         */
        $text = preg_replace('/```[a-z]*\n?/i', '', $text);
        $text = str_replace('`', '', $text);

        /*
         * Titres en début de ligne.
         */
        $text = preg_replace('/^\s{0,3}#{1,6}\s+/mu', '', $text);

        /*
         * Puces Markdown : on garde la liste, on retire le symbole.
         */
        $text = preg_replace('/^\s*[-*+]\s+/mu', '• ', $text);

        /*
         * Liens [texte](url) : seul le texte a du sens à l'oral
         * comme dans une bulle de discussion.
         */
        $text = preg_replace('/\[([^\]]+)\]\((?:[^)]+)\)/u', '$1', $text);

        return trim($text);
    }

    /**
     * Applique la politique Autopilot puis exécute l'outil.
     *
     * @return array{0: array, 1: array}
     */
    private function executeTool(array $call, ToolContext $context): array
    {
        $name = $call['name'] ?? '';

        $input = $call['input'] ?? [];

        $tool = $this->registry->get($name);

        if (!$tool) {
            return [
                [
                    'error' => true,
                    'message' => "Outil inconnu : {$name}.",
                ],
                $this->logAction($context, $name, $input, null, 'failed', 'Outil inconnu.'),
            ];
        }

        $decision = $context->policy->decide($tool);

        /*
         * Action interdite par les réglages de l'entreprise.
         */
        if ($decision === AutopilotPolicy::DENY) {
            $output = [
                'error' => true,
                'message' => "Cette action n'est pas autorisée par l'entreprise. "
                    . "Explique au client ce que tu peux faire à la place, "
                    . "ou propose un transfert vers un conseiller.",
            ];

            return [
                $output,
                $this->logAction(
                    $context,
                    $name,
                    $input,
                    $output,
                    'denied',
                    'Action non autorisée au niveau ' . $context->policy->level() . '.'
                ),
            ];
        }

        /*
         * Action soumise à validation : elle est mise en file
         * et n'est PAS exécutée maintenant.
         */
        if ($decision === AutopilotPolicy::APPROVE) {
            $action = $this->logAction(
                $context,
                $name,
                $input,
                null,
                'pending',
                'En attente de validation humaine.'
            );

            $output = [
                'queued_for_approval' => true,
                'message' => "Action enregistrée et transmise à un responsable "
                    . "pour validation. Elle n'est PAS encore effectuée : "
                    . "annonce au client que sa demande est transmise, "
                    . "sans affirmer qu'elle est traitée.",
            ];

            return [$output, $action];
        }

        /*
         * Exécution réelle.
         */
        try {
            $output = $tool->handle($input, $context);

            $action = $this->logAction(
                $context,
                $name,
                $input,
                $output,
                'executed'
            );

            return [$output, $action];
        } catch (Throwable $exception) {
            Log::error(
                "Échec de l'outil IA.",
                [
                    'tool' => $name,
                    'organization_id' => $context->organization->id,
                    'conversation_id' => $context->conversation?->id,
                    'error' => $exception->getMessage(),
                ]
            );

            $output = [
                'error' => true,
                'message' => "L'action a échoué techniquement. "
                    . "Ne promets rien au client à ce sujet.",
            ];

            return [
                $output,
                $this->logAction(
                    $context,
                    $name,
                    $input,
                    $output,
                    'failed',
                    mb_substr($exception->getMessage(), 0, 1000)
                ),
            ];
        }
    }

    /**
     * Journalise l'action dans ai_actions.
     */
    private function logAction(
        ToolContext $context,
        string $tool,
        array $input,
        ?array $output,
        string $status,
        ?string $reason = null
    ): array {
        $record = AiAction::create([
            'organization_id' => $context->organization->id,
            'conversation_id' => $context->conversation?->id,
            'client_id' => $context->client?->id,
            'ticket_id' => $context->effects['ticket_id'] ?? null,
            'message_id' => $context->message?->id,
            'tool' => $tool,
            'input' => $input,
            'output' => $output,
            'status' => $status,
            'autopilot_level' => $context->policy->level(),
            'reason' => $reason,
            'executed_at' => $status === 'executed' ? now() : null,
        ]);

        return [
            'id' => $record->id,
            'tool' => $tool,
            'status' => $status,
        ];
    }

    /**
     * Dernier appel sans outil pour obtenir un message client.
     */
    private function forceFinalAnswer(
        string $system,
        array $messages,
        array &$usage,
        bool $outOfTime = false
    ): string {
        $messages[] = [
            'role' => 'user',
            'content' => "Rédige maintenant ta réponse finale au client, "
                . "en te basant uniquement sur ce que tu as pu vérifier. "
                . "N'utilise plus d'outil."
                . ($outOfTime
                    ? " Tu manques de temps : si tu n'as pas pu vérifier "
                        . "ce qu'il fallait, dis simplement au client que "
                        . "tu vérifies et qu'on revient vers lui, sans "
                        . "inventer de réponse."
                    : ''),
        ];

        $response = $this->llm->converse($system, $messages);

        $usage['input_tokens'] += $response['usage']['input_tokens'] ?? 0;

        $usage['output_tokens'] += $response['usage']['output_tokens'] ?? 0;

        return $response['text']
            ?: "Je transmets votre demande à un conseiller qui reviendra vers vous.";
    }

    /**
     * Ajoute un tour « utilisateur » en respectant l'alternance
     * des rôles imposée par l'API.
     */
    public static function appendUserTurn(array $messages, string $content): array
    {
        $content = trim($content);

        if ($content === '') {
            return $messages;
        }

        $last = count($messages) - 1;

        if ($last >= 0 && $messages[$last]['role'] === 'user') {
            if (is_string($messages[$last]['content'])) {
                $messages[$last]['content'] .= "\n\n" . $content;

                return $messages;
            }
        }

        $messages[] = [
            'role' => 'user',
            'content' => $content,
        ];

        return $messages;
    }

    /**
     * Construit l'historique au format attendu par l'API.
     *
     * Les rôles doivent alterner : les messages consécutifs
     * d'un même interlocuteur sont fusionnés.
     */
    public function historyFor(
        Conversation $conversation,
        ?Message $message = null
    ): array {
        $limit = (int) config('ai.history_limit', 30);

        $rows = $conversation->messages()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $messages = [];

        foreach ($rows as $row) {
            $content = trim((string) $row->content);

            if ($content === '') {
                continue;
            }

            $role = match ($row->sender_type) {
                'client' => 'user',
                'ai', 'agent' => 'assistant',
                default => null,
            };

            /*
             * Les messages système servent de contexte interne
             * et sont présentés comme une note au modèle.
             */
            if ($role === null) {
                $role = 'user';

                $content = '[Note interne] ' . $content;
            }

            $last = count($messages) - 1;

            if ($last >= 0 && $messages[$last]['role'] === $role) {
                $messages[$last]['content'] .= "\n\n" . $content;

                continue;
            }

            $messages[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        /*
         * L'historique doit commencer par un message client.
         */
        while (!empty($messages) && $messages[0]['role'] !== 'user') {
            array_shift($messages);
        }

        if (!$message) {
            return $messages;
        }

        /*
         * Garantit que le message en cours de traitement
         * est bien le dernier tour utilisateur.
         */
        $current = trim((string) $message->content);

        $last = count($messages) - 1;

        if (
            $last < 0
            || $messages[$last]['role'] !== 'user'
            || !str_contains($messages[$last]['content'], $current)
        ) {
            $messages[] = [
                'role' => 'user',
                'content' => $current,
            ];
        }

        return $messages;
    }
}
