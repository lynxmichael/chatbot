<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Notifications\TicketSlaBreachedNotification;
use Illuminate\Console\Command;

class CheckTicketSlaCommand extends Command
{
    protected $signature = 'tickets:check-sla';

    protected $description = 'Notifie les agents dont un ticket a dépassé son délai SLA';

    public function handle(): int
    {
        $breachedTickets = Ticket::query()
            ->whereIn('status', ['open', 'pending', 'in_progress'])
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', now())
            ->whereNull('sla_breached_notified_at')
            ->whereNotNull('assigned_to')
            ->with('assignedAgent')
            ->get();

        foreach ($breachedTickets as $ticket) {
            $ticket->assignedAgent?->notify(
                new TicketSlaBreachedNotification($ticket)
            );

            $ticket->update([
                'sla_breached_notified_at' => now(),
            ]);
        }

        $this->info(
            "{$breachedTickets->count()} ticket(s) en dépassement de SLA notifié(s)."
        );

        return self::SUCCESS;
    }
}
