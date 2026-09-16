<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TicketSlaBreachedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ticket $ticket
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'sla_breach',
            'ticket_id' => $this->ticket->id,
            'subject' => $this->ticket->subject,
            'message' => "Le délai SLA du ticket #{$this->ticket->ticket_number} est dépassé.",
        ];
    }
}
