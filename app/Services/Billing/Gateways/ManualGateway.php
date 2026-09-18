<?php

namespace App\Services\Billing\Gateways;

use App\Models\Payment;

/**
 * Encaissement hors ligne.
 *
 * Le client règle comme il veut — virement, espèces, transfert mobile
 * reçu directement sur ton numéro — et un responsable confirme le
 * paiement depuis la console.
 *
 * C'est volontairement le mode par défaut : il permet de vendre avant
 * d'avoir un compte marchand, ce qui est l'ordre naturel des choses.
 */
class ManualGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'manual';
    }

    public function checkout(Payment $payment): ?string
    {
        /*
         * Aucune redirection : le client voit les consignes de
         * règlement dans l'application.
         */
        return null;
    }

    public function verify(Payment $payment): string
    {
        /*
         * Seul un humain sait si l'argent est arrivé.
         */
        return $payment->status === 'paid' ? 'paid' : 'pending';
    }
}
