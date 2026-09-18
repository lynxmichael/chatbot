<?php

namespace App\Services\Billing\Gateways;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * CinetPay — agrégateur de paiement d'Afrique de l'Ouest.
 *
 * Une seule intégration couvre Orange Money, MTN Money, Moov Money,
 * Wave et la carte bancaire. C'est ce qui en fait le choix pragmatique
 * ici : intégrer chaque opérateur séparément multiplierait le travail
 * sans rien apporter au client.
 *
 * Deux principes de sécurité s'appliquent, et ils ne sont pas
 * négociables :
 *
 * 1. le retour du navigateur ne prouve rien — l'utilisateur peut
 *    fabriquer cette URL lui-même ;
 * 2. la notification serveur-à-serveur ne prouve rien non plus tant
 *    qu'on n'a pas redemandé l'état du paiement à CinetPay.
 *
 * Un abonnement n'est donc accordé qu'après un appel à « verify ».
 */
class CinetPayGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'cinetpay';
    }

    public function checkout(Payment $payment): ?string
    {
        $config = $this->config();

        $response = Http::acceptJson()
            ->timeout(20)
            ->post($config['base_url'] . '/payment', [
                'apikey' => $config['api_key'],
                'site_id' => $config['site_id'],
                'transaction_id' => $payment->reference,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'description' => 'Abonnement ' . $payment->plan,

                /*
                 * Notification serveur-à-serveur : c'est elle qui fait
                 * foi, pas le retour du navigateur.
                 */
                'notify_url' => route('billing.webhook', [
                    'provider' => 'cinetpay',
                ]),

                'return_url' => route('subscription.return'),

                'channels' => 'ALL',

                'metadata' => (string) $payment->organization_id,
            ]);

        $body = $response->json();

        $url = $body['data']['payment_url'] ?? null;

        if ($response->failed() || !$url) {
            Log::error(
                'Ouverture de paiement CinetPay refusée.',
                [
                    'payment_id' => $payment->id,
                    'status' => $response->status(),
                    'body' => $body,
                ]
            );

            throw new RuntimeException(
                'Le service de paiement est momentanément indisponible.'
            );
        }

        $payment->update([
            'provider_reference' => $body['data']['payment_token'] ?? null,
        ]);

        return $url;
    }

    public function verify(Payment $payment): string
    {
        $config = $this->config();

        try {
            $response = Http::acceptJson()
                ->timeout(20)
                ->post($config['base_url'] . '/payment/check', [
                    'apikey' => $config['api_key'],
                    'site_id' => $config['site_id'],
                    'transaction_id' => $payment->reference,
                ]);
        } catch (Throwable $exception) {
            Log::warning(
                'Vérification CinetPay injoignable.',
                [
                    'payment_id' => $payment->id,
                    'error' => $exception->getMessage(),
                ]
            );

            return 'unknown';
        }

        $body = $response->json();

        $status = $body['data']['status'] ?? null;

        /*
         * Le montant est revérifié : une transaction acceptée pour un
         * montant inférieur à celui demandé n'ouvre aucun droit.
         */
        $amount = (int) ($body['data']['amount'] ?? 0);

        if ($status === 'ACCEPTED' && $amount < $payment->amount) {
            Log::warning(
                'Paiement CinetPay accepté pour un montant insuffisant.',
                [
                    'payment_id' => $payment->id,
                    'attendu' => $payment->amount,
                    'recu' => $amount,
                ]
            );

            return 'failed';
        }

        $payment->update([
            'payload' => $body,
            'method' => $body['data']['payment_method'] ?? $payment->method,
            'provider_reference' => $body['data']['operator_id']
                ?? $payment->provider_reference,
        ]);

        return match ($status) {
            'ACCEPTED' => 'paid',
            'REFUSED', 'CANCELED' => 'failed',
            'PENDING', 'WAITING_FOR_CUSTOMER' => 'pending',
            default => 'unknown',
        };
    }

    private function config(): array
    {
        $config = config('ai.billing.cinetpay', []);

        foreach (['site_id', 'api_key'] as $key) {
            if (empty($config[$key])) {
                throw new RuntimeException(
                    "Configuration CinetPay incomplète : {$key} manquant."
                );
            }
        }

        return $config;
    }
}
