<?php

namespace App\Notifications;

use App\Models\Call;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewIncomingCall extends Notification
{
    use Queueable;

    public function __construct(
        public Call $call
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'incoming_call',
            'title' => 'Nouvel appel entrant',
            'message' => 'Un nouvel appel entrant a été enregistré.',
            'call_id' => $this->call->id,
            'client_id' => $this->call->client_id,
            'client_name' => $this->call->client?->full_name,
            'phone' => $this->call->phone,
            'reason' => $this->call->reason,
            'started_at' => $this->call->started_at?->format('d/m/Y H:i'),
        ];
    }
}
