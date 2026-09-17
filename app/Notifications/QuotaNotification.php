<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Alerte de consommation.
 *
 * Envoyée une fois au franchissement du seuil d'alerte, et une fois au
 * plafond. Répéter à chaque message reviendrait à n'être lu par personne.
 */
class QuotaNotification extends Notification
{
    use Queueable;

    public function __construct(
        public array $summary,
        public bool $blocked = false,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $messages = $this->summary['ai_messages'] ?? [];

        return [
            'type' => 'quota',
            'blocked' => $this->blocked,

            'title' => $this->blocked
                ? "Plafond mensuel atteint"
                : "Plafond mensuel bientôt atteint",

            'message' => $this->blocked
                ? "L'assistant ne répond plus automatiquement : les "
                    . "demandes partent désormais vers vos agents. "
                    . "Les compteurs repartent de zéro le 1er du mois."
                : ($messages['used'] ?? 0) . ' réponses sur '
                    . ($messages['limit'] ?? 0) . ' utilisées ce mois-ci.',

            'used' => $messages['used'] ?? 0,
            'limit' => $messages['limit'] ?? 0,
            'estimated_cost' => $this->summary['estimated_cost'] ?? 0,
            'url' => '/autopilot',
        ];
    }
}
