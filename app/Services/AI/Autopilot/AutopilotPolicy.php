<?php

namespace App\Services\AI\Autopilot;

use App\Models\Organization;
use App\Services\AI\Tools\Tool;

/**
 * Traduit les réglages d'une organisation en décisions concrètes :
 * l'IA peut-elle exécuter cet outil, doit-elle demander une validation,
 * ou l'action est-elle interdite ?
 *
 * Niveaux :
 *
 * off     : l'IA ne traite rien.
 * suggest : l'IA lit et rédige, aucune écriture n'est faite.
 * assist  : l'IA lit et répond ; les écritures partent en validation.
 * auto    : l'IA exécute toutes les actions autorisées.
 */
class AutopilotPolicy
{
    public const EXECUTE = 'execute';

    public const APPROVE = 'approve';

    public const DENY = 'deny';

    private function __construct(
        private readonly array $settings
    ) {
    }

    public static function forOrganization(?Organization $organization): self
    {
        $defaults = config('ai.autopilot');

        if (!$organization) {
            return new self($defaults);
        }

        return new self($organization->aiSettings());
    }

    public function level(): string
    {
        $level = $this->settings['level'] ?? 'assist';

        return in_array(
            $level,
            ['off', 'suggest', 'assist', 'auto'],
            true
        )
            ? $level
            : 'assist';
    }

    public function isDisabled(): bool
    {
        return $this->level() === 'off';
    }

    /**
     * Mode brouillon : l'IA rédige mais rien n'est envoyé au client
     * ni écrit en base.
     */
    public function isDraftOnly(): bool
    {
        return $this->level() === 'suggest';
    }

    public function tone(): string
    {
        return $this->settings['tone']
            ?? 'professionnel, clair et chaleureux';
    }

    public function language(): string
    {
        return $this->settings['language'] ?? 'fr';
    }

    public function persona(): ?string
    {
        return $this->settings['persona'] ?? null;
    }

    public function businessName(): ?string
    {
        return $this->settings['business_name'] ?? null;
    }

    public function confidenceThreshold(): float
    {
        return (float) ($this->settings['confidence_threshold'] ?? 0.6);
    }

    public function autoCloseAfterHours(): int
    {
        return (int) ($this->settings['auto_close_after_hours'] ?? 72);
    }

    public function escalatesOnNegativeSentiment(): bool
    {
        return (bool) ($this->settings['escalate_on_negative_sentiment'] ?? true);
    }

    /**
     * Liste des outils autorisés par l'organisation.
     */
    public function allowedActions(): array
    {
        $actions = $this->settings['allowed_actions'] ?? [];

        return is_array($actions) ? $actions : [];
    }

    /**
     * Décision pour un outil donné.
     */
    public function decide(Tool $tool): string
    {
        if ($this->isDisabled()) {
            return self::DENY;
        }

        /*
         * Un outil retiré de la liste blanche n'est jamais exécuté,
         * quel que soit le niveau.
         */
        if (!in_array($tool->name(), $this->allowedActions(), true)) {
            return self::DENY;
        }

        /*
         * La lecture est toujours permise dès que l'IA est active :
         * consulter la base de connaissances ou le dossier client
         * ne modifie rien.
         */
        if (!$tool->isWrite()) {
            return self::EXECUTE;
        }

        return match ($this->level()) {
            'suggest' => self::DENY,
            'assist' => $tool->isSafeInAssistMode()
                ? self::EXECUTE
                : self::APPROVE,
            'auto' => self::EXECUTE,
            default => self::APPROVE,
        };
    }

    /**
     * Résumé lisible, injecté dans le prompt pour que l'IA sache
     * ce qu'elle peut réellement promettre au client.
     */
    public function describeForPrompt(array $tools): string
    {
        $lines = [];

        foreach ($tools as $tool) {
            $decision = $this->decide($tool);

            $lines[] = match ($decision) {
                self::EXECUTE => '- ' . $tool->name()
                    . ' : autorisé, effet immédiat.',

                self::APPROVE => '- ' . $tool->name()
                    . ' : autorisé, mais soumis à la validation d\'un responsable.'
                    . ' Ne promets pas au client que c\'est déjà fait.',

                default => '- ' . $tool->name()
                    . ' : INTERDIT. N\'essaie pas de l\'utiliser.',
            };
        }

        return implode("\n", $lines);
    }
}
