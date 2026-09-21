<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Services\AI\Contracts\LlmClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Analyse d'une conversation, en arrière-plan.
 *
 * Intention, sentiment, résumé : ces informations nourrissent la
 * supervision et le dossier préparé pour un agent. Elles étaient
 * produites par l'assistant AVANT chaque réponse, ce qui ajoutait un
 * aller-retour complet au modèle — plusieurs secondes d'attente pour le
 * client, pour des données qu'il ne voit jamais.
 *
 * Elles sont désormais calculées après la réponse, par un modèle plus
 * rapide, sans que personne n'attende.
 */
class AnalyzeConversation implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(
        public int $conversationId
    ) {
    }

    public function handle(LlmClient $llm): void
    {
        $conversation = Conversation::query()
            ->withoutGlobalScopes()
            ->find($this->conversationId);

        if (!$conversation) {
            return;
        }

        $transcript = $conversation->messages()
            ->withoutGlobalScopes()
            ->latest('id')
            ->limit(12)
            ->get()
            ->reverse()
            ->map(fn ($message) => match ($message->sender_type) {
                'client' => 'Client : ',
                'ai' => 'Assistant : ',
                'agent' => 'Agent : ',
                default => 'Note : ',
            } . Str::limit((string) $message->content, 500))
            ->implode("\n");

        if (trim($transcript) === '') {
            return;
        }

        $system = "Tu analyses une conversation de service client. Réponds "
            . "UNIQUEMENT par un objet JSON, sans texte autour, avec ces "
            . "clés : intent (quelques mots), sentiment (positive, neutral, "
            . "negative ou angry), summary (deux phrases maximum, pour un "
            . "agent qui reprendrait le dossier), confidence (nombre entre "
            . "0 et 1 : à quel point l'assistant a répondu de manière fiable).";

        try {
            $response = $llm->converse(
                $system,
                [['role' => 'user', 'content' => $transcript]],
                [],
                [
                    'model' => config('ai.analysis_model'),
                    'max_tokens' => 300,
                    'temperature' => 0,
                ]
            );
        } catch (Throwable $exception) {
            Log::warning(
                'Analyse de conversation impossible.',
                [
                    'conversation_id' => $conversation->id,
                    'error' => $exception->getMessage(),
                ]
            );

            return;
        }

        $data = $this->parse($response['text'] ?? '');

        if (!$data) {
            return;
        }

        $sentiment = in_array($data['sentiment'] ?? null, ['positive', 'neutral', 'negative', 'angry'], true)
            ? $data['sentiment']
            : 'neutral';

        $conversation->update([
            'ai_intent' => Str::limit((string) ($data['intent'] ?? ''), 180, ''),
            'ai_sentiment' => $sentiment,
            'ai_summary' => Str::limit((string) ($data['summary'] ?? ''), 1000, ''),
            'ai_confidence' => max(0, min(1, (float) ($data['confidence'] ?? 0.5))),
            'ai_last_run_at' => now(),
        ]);
    }

    /**
     * Extrait l'objet JSON, même si le modèle l'a entouré de texte ou
     * de balises de code.
     */
    private function parse(string $text): ?array
    {
        $text = trim(preg_replace('/^```(?:json)?|```$/m', '', $text));

        if (preg_match('/\{.*\}/s', $text, $match)) {
            $text = $match[0];
        }

        $data = json_decode($text, true);

        return is_array($data) ? $data : null;
    }
}
