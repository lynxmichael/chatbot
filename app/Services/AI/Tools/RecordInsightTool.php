<?php

namespace App\Services\AI\Tools;

use Illuminate\Support\Str;

class RecordInsightTool implements Tool
{
    public function name(): string
    {
        return 'record_insights';
    }

    public function description(): string
    {
        return "Enregistre ton analyse interne de la conversation : intention "
            . "du client, sentiment, résumé, niveau de confiance dans ta réponse. "
            . "À appeler une fois par échange, avant ta réponse finale. "
            . "Ces informations ne sont jamais montrées au client : elles servent "
            . "au dossier de l'agent et à la supervision.";
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'intent' => [
                    'type' => 'string',
                    'description' => "Intention principale en quelques mots : "
                        . "« suivi de commande », « demande de remboursement »...",
                ],
                'sentiment' => [
                    'type' => 'string',
                    'enum' => ['positive', 'neutral', 'negative', 'angry'],
                    'description' => 'État d\'esprit du client.',
                ],
                'summary' => [
                    'type' => 'string',
                    'description' => 'Résumé de la conversation à ce stade, '
                        . 'utile à un agent qui prendrait le relais.',
                ],
                'confidence' => [
                    'type' => 'number',
                    'description' => 'Confiance dans ta réponse, entre 0 et 1. '
                        . 'En dessous de 0,6, envisage un transfert humain.',
                ],
            ],
            'required' => ['intent', 'sentiment', 'confidence'],
        ];
    }

    public function isWrite(): bool
    {
        return true;
    }

    /**
     * Analyse interne : aucune conséquence pour le client,
     * donc exécutable sans validation.
     */
    public function isSafeInAssistMode(): bool
    {
        return true;
    }

    public function handle(array $input, ToolContext $context): array
    {
        $conversation = $context->conversation;

        $sentiment = $input['sentiment'] ?? 'neutral';

        $confidence = (float) ($input['confidence'] ?? 0.5);

        $confidence = max(0, min(1, $confidence));

        if ($conversation) {
            $conversation->update([
                'ai_intent' => Str::limit((string) ($input['intent'] ?? ''), 180, ''),
                'ai_sentiment' => $sentiment,
                'ai_summary' => $input['summary'] ?? $conversation->ai_summary,
                'ai_confidence' => $confidence,
                'ai_last_run_at' => now(),
            ]);
        }

        $context->recordEffect('sentiment', $sentiment);

        $context->recordEffect('confidence', $confidence);

        /*
         * Rappel envoyé au modèle : si la confiance est faible
         * ou le client mécontent, la règle de l'organisation
         * impose de passer la main.
         */
        $shouldEscalate = $confidence < $context->policy->confidenceThreshold()
            || (
                $context->policy->escalatesOnNegativeSentiment()
                && in_array($sentiment, ['negative', 'angry'], true)
            );

        return [
            'success' => true,
            'escalation_recommended' => $shouldEscalate,
            'message' => $shouldEscalate
                ? "Analyse enregistrée. Les règles de l'entreprise recommandent "
                    . "un transfert vers un conseiller humain."
                : 'Analyse enregistrée.',
        ];
    }
}
