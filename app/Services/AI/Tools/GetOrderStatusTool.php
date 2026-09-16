<?php

namespace App\Services\AI\Tools;

use App\Services\AI\Integrations\OrderGateway;

class GetOrderStatusTool implements Tool
{
    public function __construct(
        private readonly OrderGateway $gateway
    ) {
    }

    public function name(): string
    {
        return 'get_order_status';
    }

    public function description(): string
    {
        return "Consulte le statut réel d'une commande ou d'une livraison "
            . "dans le système de l'entreprise. À utiliser dès que le client "
            . "demande où en est sa commande. Ne jamais deviner un statut : "
            . "si l'outil ne trouve rien, le dire clairement au client.";
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reference' => [
                    'type' => 'string',
                    'description' => 'Référence ou numéro de commande donné par le client.',
                ],
                'email' => [
                    'type' => 'string',
                    'description' => 'Email associé à la commande. Facultatif.',
                ],
                'phone' => [
                    'type' => 'string',
                    'description' => 'Téléphone associé à la commande. Facultatif.',
                ],
            ],
            'required' => [],
        ];
    }

    public function isWrite(): bool
    {
        return false;
    }

    public function isSafeInAssistMode(): bool
    {
        return true;
    }

    public function handle(array $input, ToolContext $context): array
    {
        $reference = trim((string) ($input['reference'] ?? ''));

        $email = trim((string) ($input['email'] ?? ''))
            ?: $context->client?->email;

        $phone = trim((string) ($input['phone'] ?? ''))
            ?: $context->client?->phone;

        if (!$reference && !$email && !$phone) {
            return [
                'available' => true,
                'found' => false,
                'message' => 'Aucun élément de recherche. '
                    . 'Demander la référence de commande au client.',
            ];
        }

        return $this->gateway->lookup(
            $context->organization,
            $reference ?: null,
            $email,
            $phone
        );
    }
}
