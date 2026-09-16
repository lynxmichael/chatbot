<?php

namespace App\Jobs;

use App\Models\FollowUp;
use App\Models\Message;
use App\Services\AI\AgentRunner;
use App\Services\AI\Autopilot\AutopilotPolicy;
use App\Services\AI\Tools\ToolContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Exécute une relance programmée.
 *
 * L'IA ne se contente pas de renvoyer le message prévu : elle revérifie
 * la situation avec ses outils au moment de la relance, puis rédige un
 * message à jour. Une commande livrée entre-temps ne déclenche donc pas
 * un « nous vérifions votre livraison » absurde.
 */
class RunFollowUp implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public array $backoff = [30, 120];

    public function __construct(
        public int $followUpId
    ) {
    }

    public function handle(AgentRunner $agent): void
    {
        $followUp = FollowUp::with([
            'organization',
            'client',
            'conversation',
            'ticket',
        ])->find($this->followUpId);

        if (!$followUp || $followUp->status !== 'pending') {
            return;
        }

        $organization = $followUp->organization;

        if (!$organization) {
            $followUp->update([
                'status' => 'failed',
                'last_error' => 'Organisation introuvable.',
            ]);

            return;
        }

        $policy = AutopilotPolicy::forOrganization($organization);

        /*
         * Autopilot coupé entre-temps : la relance est annulée
         * plutôt que d'envoyer un message non voulu.
         */
        if ($policy->isDisabled() || $policy->isDraftOnly()) {
            $followUp->update([
                'status' => 'cancelled',
                'last_error' => "Autopilot en mode « {$policy->level()} » : relance annulée.",
            ]);

            return;
        }

        $conversation = $followUp->conversation;

        /*
         * La conversation est repassée à un humain : c'est à lui
         * de reprendre contact, pas à l'IA.
         */
        if ($conversation && !$conversation->ai_enabled) {
            $followUp->update([
                'status' => 'cancelled',
                'last_error' => 'Conversation reprise par un agent humain.',
            ]);

            return;
        }

        $followUp->increment('attempts');

        $context = new ToolContext(
            organization: $organization,
            policy: $policy,
            conversation: $conversation,
            client: $followUp->client,
            message: null,
            channel: $followUp->channel ?: 'widget',
        );

        $instruction = $this->buildInstruction($followUp);

        $messages = $conversation
            ? $agent->historyFor($conversation)
            : [];

        $messages = AgentRunner::appendUserTurn($messages, $instruction);

        try {
            $result = $agent->run($messages, $context);
        } catch (Throwable $exception) {
            $followUp->update([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);

            Log::error(
                'Échec de la relance IA.',
                [
                    'follow_up_id' => $followUp->id,
                    'error' => $exception->getMessage(),
                ]
            );

            throw $exception;
        }

        if ($conversation && trim($result->reply)) {
            Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => null,
                'sender_type' => 'ai',
                'content' => $result->reply,
                'channel' => $followUp->channel ?: $conversation->channel,
                'ai_generated' => true,
                'ai_processed' => true,
                'ai_status' => 'completed',
                'metadata' => array_merge(
                    [
                        'type' => 'follow_up',
                        'follow_up_id' => $followUp->id,
                    ],
                    $result->toMetadata()
                ),
            ]);

            $conversation->update([
                'last_message_at' => now(),
            ]);

            DeliverOutboundMessage::dispatch(
                $conversation->id,
                $result->reply
            );
        }

        $followUp->update([
            'status' => 'sent',
            'executed_at' => now(),
            'last_error' => null,
        ]);

        Log::info(
            'Relance envoyée.',
            [
                'follow_up_id' => $followUp->id,
                'conversation_id' => $conversation?->id,
                'escalated' => $result->escalated,
            ]
        );
    }

    /**
     * Consigne interne envoyée au modèle.
     *
     * Elle est explicitement présentée comme une instruction et non
     * comme un message du client, pour que l'IA ne réponde pas « à » elle.
     */
    private function buildInstruction(FollowUp $followUp): string
    {
        $lines = [
            "[INSTRUCTION INTERNE — ce n'est pas un message du client]",
            '',
            'Une relance programmée arrive à échéance.',
            '',
            'Consigne enregistrée : ' . $followUp->instruction,
        ];

        if ($followUp->ticket) {
            $lines[] = '';
            $lines[] = 'Ticket concerné : ' . $followUp->ticket->ticket_number
                . ' (statut actuel : ' . $followUp->ticket->status . ').';
        }

        $lines[] = '';
        $lines[] = "Vérifie d'abord la situation réelle avec tes outils. "
            . "Si le problème est déjà réglé, dis-le simplement et clos poliment. "
            . "Si rien n'a avancé et que tu ne peux rien faire de plus, "
            . "transfère à un conseiller.";
        $lines[] = '';
        $lines[] = "Rédige ensuite le message de relance adressé au client. "
            . "Commence par rappeler brièvement le contexte : il ne t'a pas "
            . "écrit à l'instant, c'est toi qui reviens vers lui.";

        return implode("\n", $lines);
    }

    public function failed(Throwable $exception): void
    {
        $followUp = FollowUp::find($this->followUpId);

        if ($followUp && $followUp->status === 'pending') {
            $followUp->update([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);
        }
    }
}
