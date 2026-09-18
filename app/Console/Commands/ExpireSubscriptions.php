<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionNotification;
use App\Services\Billing\Billing;
use Illuminate\Console\Command;

/**
 * Prévient avant l'échéance, rebascule après.
 */
class ExpireSubscriptions extends Command
{
    protected $signature = 'ai:subscriptions';

    protected $description = 'Alerte avant échéance et rebascule les abonnements expirés.';

    public function handle(Billing $billing): int
    {
        $grace = (int) config('ai.billing.grace_days', 3);

        /*
         * Alerte à sept jours : assez tôt pour que le client organise
         * son règlement, assez tard pour qu'il s'en souvienne.
         */
        $expiring = Subscription::query()
            ->active()
            ->whereNull('expiry_notified_at')
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [now(), now()->addDays(7)])
            ->with('organization')
            ->get();

        foreach ($expiring as $subscription) {
            $this->notify($subscription, false);

            $subscription->update(['expiry_notified_at' => now()]);

            $this->line(
                'Alerte envoyée : ' . $subscription->organization->name
                . ' (échéance ' . $subscription->ends_at->format('d/m/Y') . ')'
            );
        }

        /*
         * Rebascule après le délai de tolérance. Couper le service le
         * jour même d'un retard fait perdre des clients qui seraient
         * restés.
         */
        $expired = Subscription::query()
            ->active()
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now()->subDays($grace))
            ->with('organization')
            ->get();

        foreach ($expired as $subscription) {
            if (!$subscription->organization) {
                continue;
            }

            $billing->downgrade($subscription->organization);

            $this->notify($subscription, true);

            $this->warn(
                'Retour en gratuit : ' . $subscription->organization->name
            );
        }

        $this->info(
            $expiring->count() . ' alerte(s), '
            . $expired->count() . ' retour(s) en gratuit.'
        );

        return self::SUCCESS;
    }

    private function notify(Subscription $subscription, bool $expired): void
    {
        $owners = User::query()
            ->where('organization_id', $subscription->organization_id)
            ->where('role', 'owner')
            ->where('is_active', true)
            ->get();

        foreach ($owners as $owner) {
            $owner->notify(
                new SubscriptionNotification($subscription, $expired)
            );
        }
    }
}
