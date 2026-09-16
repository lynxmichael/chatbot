<?php

namespace App\Console\Commands;

use App\Models\AiInsight;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\SupervisorAlertNotification;
use App\Services\AI\Supervisor\SupervisorScanner;
use Illuminate\Console\Command;

class RunSupervisorScan extends Command
{
    protected $signature = 'ai:supervise
                            {--organization= : Limiter le scan à une organisation}
                            {--notify : Notifier les responsables des alertes graves}';

    protected $description = "Analyse le service client et signale ce qui dérape.";

    public function handle(SupervisorScanner $scanner): int
    {
        $query = Organization::query()
            ->where('status', '!=', 'suspended');

        if ($this->option('organization')) {
            $query->where('id', (int) $this->option('organization'));
        }

        $organizations = $query->get();

        foreach ($organizations as $organization) {
            $before = AiInsight::query()
                ->where('organization_id', $organization->id)
                ->open()
                ->pluck('id')
                ->all();

            $scanner->scan($organization);

            $open = AiInsight::query()
                ->where('organization_id', $organization->id)
                ->open()
                ->get();

            $this->line(
                $organization->name . ' : ' . $open->count() . ' alerte(s) active(s).'
            );

            if (!$this->option('notify')) {
                continue;
            }

            /*
             * On ne notifie que les alertes graves et nouvelles,
             * pour ne pas noyer les responsables.
             */
            $toNotify = $open
                ->whereIn('severity', ['high', 'critical'])
                ->whereNotIn('id', $before);

            if ($toNotify->isEmpty()) {
                continue;
            }

            $owners = User::query()
                ->where('organization_id', $organization->id)
                ->where('role', 'owner')
                ->where('is_active', true)
                ->get();

            foreach ($owners as $owner) {
                $owner->notify(
                    new SupervisorAlertNotification($toNotify->values()->all())
                );
            }

            $this->info(
                '  → ' . $toNotify->count() . ' alerte(s) grave(s) notifiée(s) à '
                . $owners->count() . ' responsable(s).'
            );
        }

        return self::SUCCESS;
    }
}
