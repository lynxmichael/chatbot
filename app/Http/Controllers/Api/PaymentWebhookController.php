<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Billing\Billing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notification de paiement, envoyée par le prestataire.
 *
 * Cette notification ne prouve rien par elle-même : elle indique
 * seulement qu'il s'est passé quelque chose. On redemande donc l'état
 * réel au prestataire avant d'accorder quoi que ce soit — c'est la
 * seule façon de ne pas ouvrir un abonnement sur une requête forgée.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly Billing $billing
    ) {
    }

    public function handle(Request $request, string $provider): JsonResponse
    {
        /*
         * CinetPay nomme ce champ « cpm_trans_id ». On accepte aussi
         * les noms génériques, pour qu'un autre prestataire se branche
         * sans modifier ce contrôleur.
         */
        $reference = $request->input('cpm_trans_id')
            ?? $request->input('transaction_id')
            ?? $request->input('reference');

        if (!$reference) {
            Log::warning(
                'Notification de paiement sans référence.',
                ['provider' => $provider, 'ip' => $request->ip()]
            );

            return response()->json(['message' => 'Missing reference'], 422);
        }

        $payment = Payment::query()
            ->where('provider', $provider)
            ->where('reference', $reference)
            ->first();

        if (!$payment) {
            Log::warning(
                'Notification pour un paiement inconnu.',
                ['provider' => $provider, 'reference' => $reference]
            );

            /*
             * On répond 200 : un code d'erreur ferait réessayer le
             * prestataire indéfiniment pour une référence qui
             * n'existera jamais.
             */
            return response()->json(['message' => 'Unknown payment']);
        }

        if ($payment->status === 'paid') {
            return response()->json(['message' => 'Already processed']);
        }

        try {
            $status = $this->billing
                ->gateway($provider)
                ->verify($payment);
        } catch (Throwable $exception) {
            Log::error(
                'Vérification du paiement impossible.',
                [
                    'payment_id' => $payment->id,
                    'error' => $exception->getMessage(),
                ]
            );

            /*
             * Ici on renvoie une erreur : le prestataire réessaiera,
             * ce qui est le comportement souhaité sur une panne
             * passagère.
             */
            return response()->json(['message' => 'Verification failed'], 503);
        }

        if ($status === 'paid') {
            $this->billing->confirm($payment, $request->all());

            return response()->json(['message' => 'Confirmed']);
        }

        if ($status === 'failed') {
            $payment->update([
                'status' => 'failed',
                'payload' => $request->all(),
            ]);
        }

        return response()->json(['message' => 'Acknowledged']);
    }
}
