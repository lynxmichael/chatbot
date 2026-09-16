<?php

namespace App\Services\AI\Tools;

use App\Models\Client;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use App\Services\AI\Autopilot\AutopilotPolicy;

/**
 * Tout ce qu'un outil a besoin de connaître pour agir,
 * et rien de plus.
 *
 * L'organisation est toujours présente : elle garantit qu'un outil
 * ne peut jamais lire ou écrire les données d'une autre entreprise.
 */
class ToolContext
{
    public function __construct(
        public readonly Organization $organization,
        public readonly AutopilotPolicy $policy,
        public readonly ?Conversation $conversation = null,
        public readonly ?Client $client = null,
        public readonly ?Message $message = null,
        public readonly string $channel = 'widget',
    ) {
    }

    /**
     * Actions réalisées pendant l'exécution courante.
     * Renseigné par l'AgentRunner pour que le Job sache
     * ce qui s'est passé (escalade, ticket créé...).
     */
    public array $effects = [];

    public function recordEffect(string $key, mixed $value): void
    {
        $this->effects[$key] = $value;
    }
}
