<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use Illuminate\Console\Command;

class CloseResolvedTicketsCommand extends Command
{
    protected $signature = 'tickets:close-resolved
        {--days= : Nombre de jours d’inactivité avant fermeture automatique}';

    protected $description = 'Ferme automatiquement les tickets résolus depuis longtemps sans nouvelle activité';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('tickets.auto_close_after_days', 3));

        $tickets = Ticket::query()
            ->where('status', 'resolved')
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '<=', now()->subDays($days))
            ->get();

        foreach ($tickets as $ticket) {
            $ticket->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);
        }

        $this->info(
            "{$tickets->count()} ticket(s) fermé(s) automatiquement (résolus depuis plus de {$days} jour(s))."
        );

        return self::SUCCESS;
    }
}
