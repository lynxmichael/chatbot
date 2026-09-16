<?php

namespace App\Jobs;

use App\Mail\AiReplyMail;
use App\Models\Conversation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Achemine une réponse vers le canal du client.
 *
 * - widget / web : rien à faire, le client lit la conversation en direct ;
 * - email : envoi d'un vrai email ;
 * - whatsapp / phone : points d'extension pour la suite.
 */
class DeliverOutboundMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(
        public int $conversationId,
        public string $body,
        public ?string $channel = null,
    ) {
    }

    public function handle(): void
    {
        $conversation = Conversation::with('client', 'organization')
            ->find($this->conversationId);

        if (!$conversation || !trim($this->body)) {
            return;
        }

        $channel = $this->channel ?: $conversation->channel;

        match ($channel) {
            'email' => $this->sendEmail($conversation),
            default => null,
        };
    }

    private function sendEmail(Conversation $conversation): void
    {
        $client = $conversation->client;

        if (!$client || !$client->email) {
            Log::warning(
                'Réponse email impossible : adresse manquante.',
                ['conversation_id' => $conversation->id]
            );

            return;
        }

        Mail::to($client->email)->send(
            new AiReplyMail(
                organizationName: $conversation->organization?->name ?? config('app.name'),
                subject: $conversation->subject ?: 'Votre demande',
                body: $this->body,
                conversationId: $conversation->id,
            )
        );

        Log::info(
            'Réponse envoyée par email.',
            [
                'conversation_id' => $conversation->id,
                'to' => $client->email,
            ]
        );
    }
}
