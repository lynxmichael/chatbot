<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\KnowledgeBase;
use App\Models\Organization;
use App\Services\AI\Autopilot\AutopilotPolicy;
use App\Services\AI\PromptBuilder;
use App\Services\AI\Tools\SearchKnowledgeTool;
use App\Services\AI\Tools\ToolContext;
use App\Services\AI\Tools\ToolRegistry;
use Illuminate\Console\Command;

/**
 * Explique pourquoi l'assistant se comporte comme il le fait.
 *
 * Quand une réponse surprend, on est tenté de relire le code. Mais la
 * cause est presque toujours dans les données : un outil absent de la
 * liste, une fiche que la recherche ne trouve pas, une migration pas
 * jouée. Cette commande vérifie chaque maillon et dit lequel rompt.
 */
class Diagnose extends Command
{
    protected $signature = 'ai:diagnose
                            {organization? : Identifiant ou nom de l\'entreprise}
                            {--query= : Question à tester, telle qu\'un client la poserait}';

    protected $description = "Diagnostique la configuration de l'assistant d'une entreprise.";

    private int $problems = 0;

    public function handle(
        ToolRegistry $registry,
        PromptBuilder $prompts
    ): int {
        $organization = $this->resolve();

        if (!$organization) {
            return self::FAILURE;
        }

        $this->line('');
        $this->info('Entreprise : ' . $organization->name . ' (#' . $organization->id . ')');
        $this->line('');

        /*
         * 1. Formule et outils.
         */
        $policy = AutopilotPolicy::forOrganization($organization);

        $this->section('Formule et outils');

        $this->line('Formule   : ' . $organization->plan());
        $this->line('Autonomie : ' . $policy->level());

        $settings = is_array($organization->ai_settings)
            ? $organization->ai_settings
            : [];

        if (isset($settings['allowed_actions'])) {
            $this->problem(
                "Liste d'outils figée dans les réglages. Elle l'emporte sur "
                . "la formule. Lancez : php artisan migrate"
            );
        }

        $allowed = $policy->allowedActions();

        foreach ($registry->all() as $tool) {
            $name = $tool->name();

            $state = match (true) {
                !in_array($name, $policy->ceiling(), true) => 'hors formule',
                !in_array($name, $allowed, true) => 'coupé par l\'entreprise',
                default => $policy->decide($tool),
            };

            $this->line(sprintf('  %-20s %s', $name, $state));
        }

        $imagesOk = in_array('send_images', $allowed, true);

        if (!$imagesOk) {
            $this->problem(
                "send_images n'est pas disponible : l'assistant ne peut pas "
                . "envoyer de photos."
            );
        }

        /*
         * 2. Consigne dans le prompt.
         */
        $this->section('Instructions données au modèle');

        $context = new ToolContext(
            organization: $organization,
            policy: $policy,
            channel: 'web',
        );

        $tools = $registry->enabledFor($allowed);

        $prompt = $prompts->build($context, $tools);

        if (str_contains($prompt, '# PHOTOS')) {
            $this->line('  Consigne sur les photos : présente');
        } elseif ($imagesOk) {
            $this->problem(
                'Consigne sur les photos absente alors que l\'outil est '
                . 'disponible. PromptBuilder.php n\'est pas à jour.'
            );
        } else {
            $this->line('  Consigne sur les photos : absente (outil indisponible)');
        }

        /*
         * 3. Base de connaissances.
         */
        $this->section('Base de connaissances');

        $entries = KnowledgeBase::acrossOrganizations()
            ->where('organization_id', $organization->id)
            ->withCount('images')
            ->get();

        $this->line(
            '  ' . $entries->where('is_active', true)->count() . ' fiche(s) active(s) sur '
            . $entries->count()
        );

        $withImages = $entries->filter(fn ($entry) => $entry->images_count > 0);

        foreach ($withImages as $entry) {
            $this->line(sprintf(
                '  %s « %s » — %d photo(s)',
                $entry->is_active ? '✓' : '✗',
                $entry->title,
                $entry->images_count
            ));

            if (!$entry->is_active) {
                $this->problem(
                    'La fiche « ' . $entry->title . ' » porte des photos mais '
                    . 'elle est désactivée : ses photos ne partiront jamais.'
                );
            }
        }

        if ($withImages->isEmpty()) {
            $this->problem('Aucune fiche ne porte de photo.');
        }

        /*
         * 4. La question du client trouve-t-elle la fiche ?
         */
        $query = $this->option('query');

        if ($query) {
            $this->section('Recherche : « ' . $query . ' »');

            $result = app(SearchKnowledgeTool::class)->handle(
                ['query' => $query],
                $context
            );

            if (!($result['found'] ?? false)) {
                $this->problem(
                    'Aucune fiche trouvée. La recherche compare les mots de '
                    . 'la question au titre et au contenu des fiches : '
                    . 'ajoutez ces mots au titre de la fiche concernée.'
                );
            } else {
                foreach ($result['results'] as $found) {
                    $images = count($found['images'] ?? []);

                    $this->line(sprintf(
                        '  « %s » — %s',
                        $found['title'],
                        $images ? $images . ' photo(s) annoncée(s)' : 'aucune photo'
                    ));
                }

                $anyImage = collect($result['results'])
                    ->contains(fn ($found) => !empty($found['images']));

                if (!$anyImage) {
                    $this->problem(
                        'La recherche trouve des fiches, mais aucune ne porte '
                        . 'de photo : l\'assistant n\'a rien à envoyer.'
                    );
                }
            }
        }

        /*
         * 5. Conversations bloquées par un transfert.
         */
        $this->section('Conversations');

        $transferred = Conversation::acrossOrganizations()
            ->where('organization_id', $organization->id)
            ->where('ai_enabled', false)
            ->whereIn('status', ['open', 'pending'])
            ->count();

        $this->line('  ' . $transferred . ' conversation(s) ouverte(s) confiée(s) à un agent.');

        if ($transferred) {
            $this->line(
                '  L\'assistant n\'y répond plus : testez dans une nouvelle '
                . 'conversation, ou effacez le stockage du widget dans le '
                . 'navigateur.'
            );
        }

        /*
         * Conclusion.
         */
        $this->line('');

        if ($this->problems === 0) {
            $this->info('Aucun problème détecté.');

            if (!$query) {
                $this->line(
                    'Ajoutez --query="la question du client" pour vérifier '
                    . 'qu\'elle trouve bien la fiche.'
                );
            }
        } else {
            $this->error($this->problems . ' problème(s) détecté(s).');
        }

        return $this->problems ? self::FAILURE : self::SUCCESS;
    }

    private function section(string $title): void
    {
        $this->line('');
        $this->line('<comment>' . $title . '</comment>');
    }

    private function problem(string $message): void
    {
        $this->problems++;

        $this->line('  <fg=red>✗ ' . $message . '</>');
    }

    private function resolve(): ?Organization
    {
        $needle = $this->argument('organization');

        if (!$needle) {
            $organization = Organization::query()->orderBy('id')->first();

            if (!$organization) {
                $this->error('Aucune entreprise enregistrée.');
            }

            return $organization;
        }

        $organization = is_numeric($needle)
            ? Organization::find((int) $needle)
            : Organization::where('name', 'like', "%{$needle}%")->first();

        if (!$organization) {
            $this->error('Entreprise introuvable : ' . $needle);
        }

        return $organization;
    }
}
