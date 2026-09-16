<?php

namespace App\Services\AI\Integrations;

use App\Models\Organization;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Point d'intégration avec le système de commandes de l'entreprise
 * (ERP, boutique en ligne, logiciel de livraison...).
 *
 * Tant qu'aucune URL n'est configurée, la passerelle répond
 * honnêtement « information indisponible » : l'IA ne doit jamais
 * inventer un statut de commande.
 *
 * Configuration attendue dans les réglages de l'organisation
 * (organizations.ai_settings) ou dans config/ai.php :
 *
 * "integrations": {
 *     "orders": {
 *         "url": "https://erp.exemple.com/api/orders/{reference}",
 *         "token": "..."
 *     }
 * }
 */
class OrderGateway
{
    public function isConfigured(Organization $organization): bool
    {
        return (bool) $this->endpoint($organization);
    }

    public function lookup(
        Organization $organization,
        ?string $reference,
        ?string $email = null,
        ?string $phone = null
    ): array {
        $endpoint = $this->endpoint($organization);

        if (!$endpoint) {
            return [
                'available' => false,
                'message' => "Le suivi des commandes n'est pas connecté. "
                    . "Impossible de consulter le statut. Ne pas inventer de réponse : "
                    . "proposer un transfert vers un agent.",
            ];
        }

        $url = str_replace(
            '{reference}',
            rawurlencode((string) $reference),
            $endpoint['url']
        );

        try {
            $response = Http::withHeaders(
                array_filter([
                    'Accept' => 'application/json',
                    'Authorization' => !empty($endpoint['token'])
                        ? 'Bearer ' . $endpoint['token']
                        : null,
                ])
            )
                ->timeout(15)
                ->get($url, array_filter([
                    'reference' => $reference,
                    'email' => $email,
                    'phone' => $phone,
                ]));

            if ($response->failed()) {
                return [
                    'available' => false,
                    'message' => 'Le système de commandes a répondu une erreur ('
                        . $response->status() . ').',
                ];
            }

            $data = $response->json();

            if (!is_array($data) || empty($data)) {
                return [
                    'available' => true,
                    'found' => false,
                    'message' => 'Aucune commande trouvée avec cette référence.',
                ];
            }

            return [
                'available' => true,
                'found' => true,
                'order' => $data,
            ];
        } catch (Throwable $exception) {
            Log::warning(
                'Échec de la consultation du système de commandes.',
                [
                    'organization_id' => $organization->id,
                    'error' => $exception->getMessage(),
                ]
            );

            return [
                'available' => false,
                'message' => 'Le système de commandes est momentanément injoignable.',
            ];
        }
    }

    /**
     * Résout la configuration : réglages de l'organisation d'abord,
     * configuration globale ensuite.
     */
    private function endpoint(Organization $organization): ?array
    {
        $settings = $organization->aiSettings();

        $candidate = $settings['integrations']['orders']
            ?? config('ai.integrations.orders');

        if (!is_array($candidate) || empty($candidate['url'])) {
            return null;
        }

        return $candidate;
    }
}
