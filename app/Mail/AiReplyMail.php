<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AiReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $organizationName,
        public string $subject,
        public string $body,
        public int $conversationId,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Re : ' . $this->subject,
            /*
             * L'identifiant de conversation est glissé dans les en-têtes
             * pour rattacher automatiquement les réponses du client.
             */
            using: [
                function ($message) {
                    $message->getHeaders()->addTextHeader(
                        'X-Conversation-Id',
                        (string) $this->conversationId
                    );
                },
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ai-reply',
            with: [
                'organizationName' => $this->organizationName,
                'body' => $this->body,
                'conversationId' => $this->conversationId,
            ],
        );
    }
}
