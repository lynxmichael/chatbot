<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Billing\Billing;
use Illuminate\Console\Command;

/**
 * Confirmation des règlements reçus hors ligne.
 *
 * Tant que l'encaissement se fait par virement ou transfert mobile
 * direct, c'est ici qu'un abonnement s'active.
 */
class ManagePayments extends Command
{
    protected $signature = 'ai:payments
                            {reference? : Référence du règlement à confirmer}
                            {--method= : Moyen employé (wave, orange-money, virement…)}';

    protected $description = 'Liste les règlements en attente et les confirme.';

    public function handle(Billing $billing): int
    {
        $reference = $this->argument('reference');

        if (!$reference) {
            return $this->pending();
        }

        $payment = Payment::where('reference', $reference)->first();

        if (!$payment) {
            $this->error('Référence introuvable : ' . $reference);

            return self::FAILURE;
        }

        if ($payment->status === 'paid') {
            $this->warn(
                'Ce règlement est déjà confirmé ('
                . $payment->paid_at->format('d/m/Y') . ').'
            );

            return self::SUCCESS;
        }

        $this->line('');
        $this->line('Entreprise : ' . $payment->organization->name);
        $this->line('Formule    : ' . $payment->plan);
        $this->line(
            'Montant    : ' . number_format($payment->amount, 0, ',', ' ')
            . ' ' . $payment->currency
        );
        $this->line('');

        if (!$this->confirm('Confirmer la réception de ce règlement ?')) {
            $this->line('Annulé.');

            return self::SUCCESS;
        }

        if ($this->option('method')) {
            $payment->update(['method' => $this->option('method')]);
        }

        $subscription = $billing->confirm($payment);

        $this->info(
            $payment->organization->name . ' passe en formule « '
            . $subscription->plan . " » jusqu'au "
            . $subscription->ends_at->format('d/m/Y') . '.'
        );

        return self::SUCCESS;
    }

    private function pending(): int
    {
        $payments = Payment::query()
            ->where('status', 'pending')
            ->with('organization')
            ->latest('id')
            ->get();

        if ($payments->isEmpty()) {
            $this->info('Aucun règlement en attente.');

            return self::SUCCESS;
        }

        $this->table(
            ['Référence', 'Entreprise', 'Formule', 'Montant', 'Demandé le'],
            $payments->map(fn (Payment $payment) => [
                $payment->reference,
                $payment->organization?->name ?? '—',
                $payment->plan,
                number_format($payment->amount, 0, ',', ' ')
                . ' ' . $payment->currency,
                $payment->created_at->format('d/m/Y'),
            ])->all()
        );

        $this->line('');
        $this->line('Confirmer : php artisan ai:payments AB-260918-XXXXXXXX');

        return self::SUCCESS;
    }
}
