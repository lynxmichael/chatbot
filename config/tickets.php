<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Délais SLA par priorité (en minutes)
    |--------------------------------------------------------------------------
    |
    | Temps maximum avant la première réponse attendue sur un ticket,
    | selon sa priorité (déterminée automatiquement par l'IA ou choisie
    | manuellement par un agent). Ajustable directement ici ou via les
    | variables d'environnement ci-dessous, sans toucher au code.
    |
    */

    'sla' => [
        'urgent' => env('TICKET_SLA_URGENT_MINUTES', 60),
        'high' => env('TICKET_SLA_HIGH_MINUTES', 240),
        'normal' => env('TICKET_SLA_NORMAL_MINUTES', 1440),
        'low' => env('TICKET_SLA_LOW_MINUTES', 4320),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fermeture automatique
    |--------------------------------------------------------------------------
    |
    | Nombre de jours d'inactivité après passage au statut "resolved"
    | avant que le ticket soit fermé automatiquement.
    |
    */

    'auto_close_after_days' => env('TICKET_AUTO_CLOSE_DAYS', 3),

];
