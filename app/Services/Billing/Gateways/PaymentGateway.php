<?php

namespace App\Services\Billing\Gateways;

use App\Models\Payment;

interface PaymentGateway
{
    /**
     * Nom court du prestataire, tel qu'enregistré sur le paiement.
     */
    public function name(): string;

    /**
     * Prépare le paiement et retourne l'adresse où envoyer le client.
     *
     * Retourne null quand aucune redirection n'est nécessaire : c'est
     * le cas du mode manuel, où le règlement se fait hors ligne.
     */
    public function checkout(Payment $payment): ?string;

    /**
     * Interroge le prestataire sur l'état réel d'un paiement.
     *
     * Sert de filet quand la notification n'arrive pas — coupure
     * réseau, webhook mal configuré — et de vérification avant
     * d'accorder quoi que ce soit.
     *
     * Retour : 'paid', 'pending', 'failed' ou 'unknown'.
     */
    public function verify(Payment $payment): string;
}
