<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HumanTransferNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Conversation $conversation
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'human_transfer',
            'conversation_id' => $this->conversation->id,
            'client_id' => $this->conversation->client_id,
            'subject' => $this->conversation->subject,
            'message' => 'Une conversation nécessite l’intervention d’un agent humain.',
        ];
    }
}
