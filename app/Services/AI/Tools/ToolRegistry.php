<?php

namespace App\Services\AI\Tools;

/**
 * Catalogue des outils mis à la disposition de l'IA.
 *
 * Pour ajouter une capacité, il suffit de créer une classe Tool
 * et de l'enregistrer ici, puis de l'ajouter à la liste
 * allowed_actions de config/ai.php.
 */
class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function __construct(
        SearchKnowledgeTool $searchKnowledge,
        GetClientProfileTool $clientProfile,
        GetOrderStatusTool $orderStatus,
        GetTicketStatusTool $ticketStatus,
        CreateTicketTool $createTicket,
        UpdateTicketTool $updateTicket,
        ScheduleFollowUpTool $scheduleFollowUp,
        EscalateToHumanTool $escalate,
        RecordInsightTool $recordInsights
    ) {
        foreach ([
            $searchKnowledge,
            $clientProfile,
            $orderStatus,
            $ticketStatus,
            $createTicket,
            $updateTicket,
            $scheduleFollowUp,
            $escalate,
            $recordInsights,
        ] as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    /**
     * @return array<string, Tool>
     */
    public function all(): array
    {
        return $this->tools;
    }

    public function get(string $name): ?Tool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Outils réellement proposés au modèle : uniquement ceux
     * que l'organisation a autorisés.
     *
     * @return array<int, Tool>
     */
    public function enabledFor(array $allowedActions): array
    {
        return array_values(
            array_filter(
                $this->tools,
                fn (Tool $tool) => in_array($tool->name(), $allowedActions, true)
            )
        );
    }

    /**
     * Traduit une liste d'outils au format attendu par l'API.
     */
    public function toSchema(array $tools): array
    {
        return array_map(
            fn (Tool $tool) => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'input_schema' => $tool->schema(),
            ],
            $tools
        );
    }
}
