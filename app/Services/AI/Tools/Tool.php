<?php

namespace App\Services\AI\Tools;

interface Tool
{
    /**
     * Nom technique utilisé par le modèle : create_ticket, etc.
     */
    public function name(): string;

    /**
     * Description lue par le modèle pour savoir quand l'utiliser.
     */
    public function description(): string;

    /**
     * Schéma JSON des paramètres attendus.
     */
    public function schema(): array;

    /**
     * true si l'outil modifie des données.
     * Les outils d'écriture sont soumis à l'Autopilot.
     */
    public function isWrite(): bool;

    /**
     * true si l'écriture reste acceptable sans validation humaine
     * en mode « assist » (exemple : escalader vers un agent,
     * enregistrer une analyse interne).
     */
    public function isSafeInAssistMode(): bool;

    /**
     * Exécute l'outil et retourne le résultat transmis au modèle.
     *
     * Le tableau retourné doit rester court et factuel :
     * il est réinjecté dans le contexte du modèle.
     */
    public function handle(array $input, ToolContext $context): array;
}
