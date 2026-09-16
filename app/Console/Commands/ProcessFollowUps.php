<?php

namespace App\Console\Commands;

use App\Jobs\RunFollowUp;
use App\Models\FollowUp;
use Illuminate\Console\Command;

class ProcessFollowUps extends Command
{
    protected $signature = 'ai:follow-ups
                            {--limit=100 : Nombre maximum de relances traitées}';

    protected $description = 'Envoie les relances programmées arrivées à échéance.';

    public function handle(): int
    {
        $followUps = FollowUp::query()
            ->due()
            ->orderBy('run_at')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($followUps->isEmpty()) {
            $this->info('Aucune relance à traiter.');

            return self::SUCCESS;
        }

        foreach ($followUps as $followUp) {
            RunFollowUp::dispatch($followUp->id);
        }

        $this->info($followUps->count() . ' relance(s) mise(s) en file.');

        return self::SUCCESS;
    }
}
