<?php

namespace App\Services\AI;

/**
 * Résultat complet d'un passage de l'agent :
 * la réponse au client, ce qui a été fait, et pourquoi.
 */
class AgentResult
{
    public function __construct(
        public readonly string $reply,
        public readonly bool $escalated = false,
        public readonly bool $isDraft = false,
        public readonly array $effects = [],
        public readonly array $actions = [],
        public readonly int $steps = 0,
        public readonly array $usage = [],
    ) {
    }

    public function sentiment(): ?string
    {
        return $this->effects['sentiment'] ?? null;
    }

    public function confidence(): ?float
    {
        return isset($this->effects['confidence'])
            ? (float) $this->effects['confidence']
            : null;
    }

    /**
     * Photos jointes par l'assistant à cette réponse.
     */
    public function attachments(): array
    {
        return $this->effects['attachments'] ?? [];
    }

    public function ticketNumber(): ?string
    {
        return $this->effects['ticket_number'] ?? null;
    }

    public function assignedAgentId(): ?int
    {
        return $this->effects['assigned_to'] ?? null;
    }

    /**
     * Actions mises en attente de validation humaine.
     */
    public function pendingActions(): array
    {
        return array_values(
            array_filter(
                $this->actions,
                fn (array $action) => ($action['status'] ?? null) === 'pending'
            )
        );
    }

    public function toMetadata(): array
    {
        return [
            'escalated' => $this->escalated,
            'is_draft' => $this->isDraft,
            'steps' => $this->steps,
            'sentiment' => $this->sentiment(),
            'confidence' => $this->confidence(),
            'ticket_number' => $this->ticketNumber(),
            'attachments' => $this->attachments(),
            'tools_used' => array_values(
                array_unique(
                    array_map(
                        fn (array $action) => $action['tool'] ?? '',
                        $this->actions
                    )
                )
            ),
            'usage' => $this->usage,
        ];
    }
}
