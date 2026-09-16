<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Résumé des alertes graves détectées par la supervision.
 */
class SupervisorAlertNotification extends Notification
{
    use Queueable;

    /**
     * @param array $insights Liste de modèles AiInsight.
     */
    public function __construct(
        public array $insights
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'supervisor_alert',
            'count' => count($this->insights),
            'title' => count($this->insights) === 1
                ? 'Une alerte demande votre attention'
                : count($this->insights) . ' alertes demandent votre attention',
            'items' => array_map(
                fn ($insight) => [
                    'id' => $insight->id,
                    'type' => $insight->type,
                    'severity' => $insight->severity,
                    'title' => $insight->title,
                    'detail' => $insight->detail,
                    'subject_type' => $insight->subject_type,
                    'subject_id' => $insight->subject_id,
                ],
                $this->insights
            ),
            'url' => '/autopilot/insights',
        ];
    }
}
