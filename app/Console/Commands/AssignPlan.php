<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\AI\Usage\UsageMeter;
use Illuminate\Console\Command;

/**
 * Change la formule d'une entreprise.
 *
 * Sans argument, affiche qui est sur quelle formule : c'est la vue
 * dont on a besoin en premier quand on gère plusieurs clients.
 */
class AssignPlan extends Command
{
    protected $signature = 'ai:plan
                            {organization? : Identifiant ou nom de l\'entreprise}
                            {plan? : free, pro, business…}';

    protected $description = 'Consulte ou change la formule d\'une entreprise.';

    public function handle(UsageMeter $usage): int
    {
        $plans = array_keys(config('ai.plans', []));

        if (!$this->argument('organization')) {
            return $this->overview($usage, $plans);
        }

        $organization = $this->resolve($this->argument('organization'));

        if (!$organization) {
            return self::FAILURE;
        }

        if (!$this->argument('plan')) {
            return $this->detail($organization, $usage);
        }

        $plan = $this->argument('plan');

        if (!in_array($plan, $plans, true)) {
            $this->error(
                'Formule inconnue. Disponibles : ' . implode(', ', $plans)
            );

            return self::FAILURE;
        }

        /*
         * Seule la formule change. Les réglages propres de l'entreprise
         * — un plafond relevé, un ton particulier — sont conservés :
         * les écraser à chaque changement de formule reviendrait à
         * perdre les exceptions accordées.
         */
        $organization->update([
            'ai_settings' => array_merge(
                is_array($organization->ai_settings)
                    ? $organization->ai_settings
                    : [],
                ['plan' => $plan]
            ),
        ]);

        $this->info(
            $organization->name . ' passe en formule « ' . $plan . ' ».'
        );

        $this->line('');

        return $this->detail($organization->fresh(), $usage);
    }

    /**
     * Qui est sur quelle formule.
     */
    private function overview(UsageMeter $usage, array $plans): int
    {
        $rows = Organization::query()
            ->orderBy('name')
            ->get()
            ->map(function (Organization $organization) use ($usage) {
                $summary = $usage->summary($organization);

                $messages = $summary['ai_messages'];

                return [
                    $organization->id,
                    $organization->name,
                    $summary['plan'],
                    $messages['unlimited']
                        ? $messages['used'] . ' / ∞'
                        : $messages['used'] . ' / ' . $messages['limit'],
                    $summary['voice_calls']['blocked']
                        ? 'fermée'
                        : $summary['voice_calls']['used'],
                    '$' . number_format($summary['estimated_cost'], 2),
                ];
            })
            ->all();

        if (empty($rows)) {
            $this->warn('Aucune entreprise enregistrée.');

            return self::SUCCESS;
        }

        $this->table(
            ['#', 'Entreprise', 'Formule', 'Messages', 'Appels', 'Coût'],
            $rows
        );

        $this->line('');
        $this->line('Formules disponibles : ' . implode(', ', $plans));
        $this->line('Changer : php artisan ai:plan 1 pro');

        return self::SUCCESS;
    }

    /**
     * Ce que la formule donne réellement à cette entreprise.
     */
    private function detail(Organization $organization, UsageMeter $usage): int
    {
        $settings = $organization->aiSettings();

        $summary = $usage->summary($organization);

        $this->line('Entreprise : ' . $organization->name);
        $this->line('Formule    : ' . $summary['plan']);
        $this->line('Autonomie  : ' . ($settings['level'] ?? 'assist'));

        $messages = $summary['ai_messages'];

        $this->line(
            'Messages   : ' . ($messages['unlimited']
                ? 'illimités'
                : $messages['used'] . ' sur ' . $messages['limit'])
        );

        $voice = $summary['voice_calls'];

        $this->line(
            'Appels     : ' . match (true) {
                $voice['blocked'] => 'fermés',
                $voice['unlimited'] => 'illimités',
                default => $voice['used'] . ' sur ' . $voice['limit'],
            }
        );

        $this->line('');
        $this->line('Outils autorisés :');

        foreach ($settings['allowed_actions'] ?? [] as $action) {
            $this->line('  - ' . $action);
        }

        $this->line('');

        return self::SUCCESS;
    }

    private function resolve(string $needle): ?Organization
    {
        $organization = is_numeric($needle)
            ? Organization::find((int) $needle)
            : Organization::where('name', 'like', "%{$needle}%")->first();

        if (!$organization) {
            $this->error('Entreprise introuvable : ' . $needle);
        }

        return $organization;
    }
}
