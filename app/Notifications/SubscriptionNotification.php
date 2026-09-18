<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubscriptionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public bool $expired = false,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $days = $this->subscription->daysRemaining();

        return [
            'type' => 'subscription',
            'expired' => $this->expired,

            'title' => $this->expired
                ? 'Abonnement échu'
                : 'Votre abonnement arrive à échéance',

            'message' => $this->expired
                ? "Votre formule est revenue en Découverte. Les plafonds "
                    . "gratuits s'appliquent de nouveau."
                : "Il reste " . max(0, (int) $days) . " jour(s) avant "
                    . "l'échéance de votre formule "
                    . $this->subscription->plan . '.',

            'plan' => $this->subscription->plan,
            'ends_at' => optional($this->subscription->ends_at)->toDateString(),
            'url' => '/subscription',
        ];
    }
}
