<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\Ticket;
use App\Services\AI\Autopilot\AutopilotPolicy;
use Illuminate\Console\Command;

/**
 * Ferme les tickets résolus depuis assez longtemps.
 *
 * Un ticket « resolved » signifie que la solution a été apportée.
 * S'il reste sans réaction du client pendant le délai configuré,
 * il est clos pour ne pas encombrer les files des agents.
 */
class AutoCloseTickets extends Command
{
    protected $signature = 'ai:auto-close';

    protected $description = 'Clôture automatiquement les tickets résolus sans retour du client.';

    public function handle(): int
    {
        $total = 0;

        Organization::query()
            ->where('status', '!=', 'suspended')
            ->chunkById(50, function ($organizations) use (&$total) {
                foreach ($organizations as $organization) {
                    $policy = AutopilotPolicy::forOrganization($organization);

                    if ($policy->isDisabled()) {
                        continue;
                    }

                    $hours = $policy->autoCloseAfterHours();

                    if ($hours <= 0) {
                        continue;
                    }

                    $closed = Ticket::query()
                        ->where('organization_id', $organization->id)
                        ->where('status', 'resolved')
                        ->whereNotNull('resolved_at')
                        ->where('resolved_at', '<=', now()->subHours($hours))
                        ->update([
                            'status' => 'closed',
                            'closed_at' => now(),
                        ]);

                    if ($closed > 0) {
                        $this->line(
                            $organization->name . ' : ' . $closed . ' ticket(s) clos.'
                        );
                    }

                    $total += $closed;
                }
            });

        $this->info($total . ' ticket(s) clôturé(s) au total.');

        return self::SUCCESS;
    }
}
