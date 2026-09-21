<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\User;
use App\Notifications\HumanTransferNotification;
use App\Services\AI\AgentResult;
use App\Services\AI\AgentRunner;
use App\Services\AI\Autopilot\AutopilotPolicy;
use App\Services\AI\Usage\UsageMeter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessIncomingMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public array $backoff = [5, 15, 30];

    public function __construct(
        public int $messageId
    ) {
    }

    public function handle(AgentRunner $agent, UsageMeter $usage): void
    {
        $message = Message::with('conversation.organization', 'conversation.client')
            ->find($this->messageId);

        if (!$message) {
            Log::warning(
                'Message introuvable pour le traitement IA.',
                ['message_id' => $this->messageId]
            );

            return;
        }

        /*
         * Un message déjà traité ne doit pas générer une seconde réponse.
         */
        if (
            $message->ai_status === 'completed'
            || $message->ai_processed
        ) {
            return;
        }

        if ($message->sender_type !== 'client') {
            Log::warning(
                'Message ignoré : expéditeur non client.',
                [
                    'message_id' => $message->id,
                    'sender_type' => $message->sender_type,
                ]
            );

            return;
        }

        $conversation = $message->conversation;

        if (!$conversation) {
            Log::warning(
                'Conversation introuvable pour le message IA.',
                ['message_id' => $message->id]
            );

            return;
        }

        if (!$conversation->ai_enabled) {
            $message->update([
                'ai_status' => 'failed',
                'ai_error' => "L'IA est désactivée pour cette conversation.",
            ]);

            return;
        }

        $policy = AutopilotPolicy::forOrganization($conversation->organization);

        /*
         * Autopilot coupé : aucune réponse automatique.
         */
        if ($policy->isDisabled()) {
            $message->update([
                'ai_status' => 'skipped',
                'ai_error' => "Autopilot désactivé pour cette organisation.",
            ]);

            Log::info(
                'Traitement IA ignoré : Autopilot désactivé.',
                [
                    'message_id' => $message->id,
                    'organization_id' => $conversation->organization_id,
                ]
            );

            return;
        }

        /*
         * Plafond mensuel atteint : plutôt que de laisser le client sans
         * réponse, la conversation passe à un agent humain. Le service
         * continue, c'est l'automatisation qui s'arrête.
         */
        if (!$usage->allows($conversation->organization, 'ai_messages')) {
            $this->handOverForQuota($message, $usage);

            return;
        }

        $message->update([
            'ai_status' => 'processing',
            'ai_error' => null,
        ]);

        Log::info(
            'Début du traitement IA.',
            [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'organization_id' => $conversation->organization_id,
                'autopilot_level' => $policy->level(),
                'model' => config('ai.model'),
            ]
        );

        try {
            $result = $agent->forConversation($conversation, $message);
        } catch (Throwable $exception) {
            $message->update([
                'ai_status' => 'failed',
                'ai_error' => mb_substr($exception->getMessage(), 0, 5000),
            ]);

            Log::error(
                'Erreur pendant le traitement IA.',
                [
                    'message_id' => $message->id,
                    'conversation_id' => $conversation->id,
                    'error' => $exception->getMessage(),
                ]
            );

            throw $exception;
        }

        /*
         * Consommation enregistrée après coup : on compte ce qui a été
         * réellement dépensé, jetons compris.
         */
        $usage->record($conversation->organization, [
            'ai_messages' => 1,
            'input_tokens' => $result->usage['input_tokens'] ?? 0,
            'output_tokens' => $result->usage['output_tokens'] ?? 0,
        ]);

        if ($usage->shouldWarn($conversation->organization)) {
            $this->warnOwners($conversation, $usage);
        }

        $this->persist($message, $result);

        /*
         * Acheminement vers le canal du client.
         *
         * Sur le widget, le client lit la conversation en direct ;
         * par email, il faut un vrai envoi. Un brouillon n'est
         * jamais expédié.
         */
        if (!$result->isDraft && trim($result->reply)) {
            DeliverOutboundMessage::dispatch(
                $conversation->id,
                $result->reply,
                $message->channel
            );
        }

        /*
         * Analyse de la conversation, après coup : elle ne sert qu'à la
         * supervision et au dossier des agents, le client n'a pas à
         * l'attendre. Légèrement différée pour ne pas concurrencer le
         * message suivant du client.
         */
        if (!$result->isDraft) {
            AnalyzeConversation::dispatch($conversation->id)
                ->delay(now()->addSeconds(5));
        }

        /*
         * Notifications envoyées hors transaction, une fois
         * les écritures confirmées.
         */
        if ($result->escalated) {
            $this->notifyAgents($message, $result);
        }

        if ($result->pendingActions()) {
            Log::info(
                'Actions IA en attente de validation.',
                [
                    'conversation_id' => $conversation->id,
                    'actions' => $result->pendingActions(),
                ]
            );
        }

        Log::info(
            'Traitement IA terminé.',
            [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'escalated' => $result->escalated,
                'is_draft' => $result->isDraft,
                'steps' => $result->steps,
                'sentiment' => $result->sentiment(),
                'confidence' => $result->confidence(),
            ]
        );
    }

    /**
     * Bascule la conversation vers un humain, plafond atteint.
     */
    private function handOverForQuota(Message $message, UsageMeter $usage): void
    {
        $conversation = $message->conversation;

        $organization = $conversation->organization;

        $conversation->update([
            'status' => 'open',
            'ai_enabled' => false,
        ]);

        $message->update([
            'ai_processed' => true,
            'ai_status' => 'skipped',
            'ai_error' => 'Plafond mensuel atteint : transfert vers un agent.',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => null,
            'sender_type' => 'system',
            'content' => "Plafond mensuel de l'assistant atteint. "
                . "Cette conversation attend un agent.",
            'channel' => $message->channel,
            'ai_generated' => false,
            'ai_processed' => true,
            'ai_status' => 'completed',
            'metadata' => ['reason' => 'quota_exceeded'],
        ]);

        if ($usage->markBlocked($organization)) {
            $this->warnOwners($conversation, $usage, blocked: true);
        }

        Log::warning(
            'Plafond IA atteint.',
            [
                'organization_id' => $organization->id,
                'conversation_id' => $conversation->id,
                'consumed' => $usage->consumed($organization, 'ai_messages'),
            ]
        );
    }

    /**
     * Prévient les responsables.
     */
    private function warnOwners(
        $conversation,
        UsageMeter $usage,
        bool $blocked = false
    ): void {
        $owners = User::query()
            ->where('organization_id', $conversation->organization_id)
            ->where('role', 'owner')
            ->where('is_active', true)
            ->get();

        foreach ($owners as $owner) {
            $owner->notify(
                new \App\Notifications\QuotaNotification(
                    summary: $usage->summary($conversation->organization),
                    blocked: $blocked,
                )
            );
        }
    }

    /**
     * Enregistre la réponse produite par l'agent.
     */
    private function persist(Message $message, AgentResult $result): void
    {
        DB::transaction(function () use ($message, $result) {
            $locked = Message::query()
                ->lockForUpdate()
                ->find($message->id);

            if (!$locked) {
                return;
            }

            /*
             * Une autre tentative a déjà enregistré la réponse.
             */
            if (
                $locked->ai_status === 'completed'
                || $locked->ai_processed
            ) {
                return;
            }

            $conversation = $message->conversation;

            /*
             * En mode « suggest », la réponse est un brouillon :
             * elle est stockée mais n'est pas adressée au client.
             */
            Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => null,
                'sender_type' => $result->isDraft ? 'system' : 'ai',
                'content' => $result->reply,
                'channel' => $message->channel,
                'ai_generated' => true,
                'ai_processed' => true,
                'ai_status' => 'completed',
                'ai_error' => null,
                'metadata' => array_merge(
                    [
                        'provider' => config('ai.provider'),
                        'model' => config('ai.model'),
                        'source_message_id' => $message->id,
                    ],
                    $result->toMetadata()
                ),
            ]);

            $locked->update([
                'ai_processed' => true,
                'ai_status' => 'completed',
                'ai_error' => null,
            ]);

            $conversation->update([
                'last_message_at' => now(),
            ]);
        });
    }

    /**
     * Prévient les agents qu'une conversation leur revient.
     */
    private function notifyAgents(Message $message, AgentResult $result): void
    {
        $conversation = $message->conversation;

        $assignedId = $result->assignedAgentId();

        $recipients = User::query()
            ->where('organization_id', $conversation->organization_id)
            ->where('is_active', true)
            ->when(
                $assignedId,
                fn ($query) => $query->where(function ($inner) use ($assignedId) {
                    $inner->where('id', $assignedId)
                        ->orWhere('role', 'owner');
                }),
                fn ($query) => $query->whereIn('role', ['owner', 'agent'])
            )
            ->get();

        foreach ($recipients as $recipient) {
            $recipient->notify(
                new HumanTransferNotification($conversation)
            );
        }

        Log::info(
            'Agents notifiés du transfert humain.',
            [
                'conversation_id' => $conversation->id,
                'recipients' => $recipients->count(),
                'assigned_to' => $assignedId,
            ]
        );
    }

    public function failed(Throwable $exception): void
    {
        $message = Message::find($this->messageId);

        if ($message) {
            $message->update([
                'ai_status' => 'failed',
                'ai_error' => mb_substr($exception->getMessage(), 0, 5000),
            ]);
        }

        Log::error(
            'Le traitement IA a définitivement échoué.',
            [
                'message_id' => $this->messageId,
                'error' => $exception->getMessage(),
            ]
        );
    }
}
