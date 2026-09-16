<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fournisseur et modèle
    |--------------------------------------------------------------------------
    */

    'provider' => env('AI_PROVIDER', 'anthropic'),

    'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),

    'max_tokens' => (int) env('AI_MAX_TOKENS', 2048),

    'temperature' => (float) env('AI_TEMPERATURE', 0.2),

    'timeout' => (int) env('AI_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Boucle d'agent
    |--------------------------------------------------------------------------
    |
    | Nombre maximum d'allers-retours « réflexion -> outil -> réflexion »
    | autorisés pour une seule demande client.
    |
    */

    'max_steps' => (int) env('AI_MAX_STEPS', 6),

    /*
    |--------------------------------------------------------------------------
    | Historique transmis au modèle
    |--------------------------------------------------------------------------
    |
    | Nombre de messages passés réinjectés dans le contexte.
    |
    */

    'history_limit' => (int) env('AI_HISTORY_LIMIT', 30),

    /*
    |--------------------------------------------------------------------------
    | Réglages par défaut de l'Autopilot
    |--------------------------------------------------------------------------
    |
    | Ces valeurs s'appliquent lorsqu'une organisation n'a pas encore
    | défini ses propres réglages dans organizations.ai_settings.
    |
    | Niveaux :
    |
    | off     : l'IA ne répond pas.
    | suggest : l'IA rédige, aucune action n'est exécutée.
    | assist  : l'IA répond et lit les données ; les actions d'écriture
    |           passent en file de validation.
    | auto    : l'IA exécute toutes les actions autorisées.
    |
    */

    'autopilot' => [

        'level' => env('AI_AUTOPILOT_LEVEL', 'assist'),

        'allowed_actions' => [
            'search_knowledge',
            'get_client_profile',
            'get_order_status',
            'get_ticket_status',
            'record_insights',
            'create_ticket',
            'update_ticket',
            'schedule_follow_up',
            'escalate_to_human',
        ],

        'tone' => 'professionnel, clair et chaleureux',

        'language' => 'fr',

        'confidence_threshold' => 0.6,

        'auto_close_after_hours' => 72,

        'escalate_on_negative_sentiment' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | SLA par priorité (en minutes ouvrées)
    |--------------------------------------------------------------------------
    */

    'sla' => [

        'first_response' => [
            'urgent' => 15,
            'high' => 60,
            'normal' => 240,
            'low' => 480,
        ],

        'resolution' => [
            'urgent' => 240,
            'high' => 480,
            'normal' => 1440,
            'low' => 2880,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Heures ouvrées
    |--------------------------------------------------------------------------
    |
    | Utilisées pour le calcul des SLA. 1 = lundi ... 7 = dimanche.
    |
    */

    'business_hours' => [

        'timezone' => env('AI_TIMEZONE', 'Africa/Abidjan'),

        'days' => [1, 2, 3, 4, 5, 6],

        'start' => '08:00',

        'end' => '18:00',
    ],

    /*
    |--------------------------------------------------------------------------
    | Canal téléphonique
    |--------------------------------------------------------------------------
    |
    | La transcription et la synthèse vocale sont assurées par l'opérateur
    | (Twilio : Gather input="speech" et Say). Aucun service externe
    | supplémentaire n'est nécessaire.
    |
    | fallback_number : numéro vers lequel basculer quand l'IA passe la main.
    | Si vide, le téléphone de l'organisation est utilisé.
    |
    */

    'voice' => [

        'language' => env('AI_VOICE_LANGUAGE', 'fr-FR'),

        'tts_voice' => env('AI_VOICE_TTS', 'Google.fr-FR-Standard-A'),

        'fallback_number' => env('AI_VOICE_FALLBACK_NUMBER'),

        /*
         * Moins d'étapes qu'à l'écrit : le client patiente en ligne.
         */
        'max_steps' => (int) env('AI_VOICE_MAX_STEPS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Intégrations métier
    |--------------------------------------------------------------------------
    |
    | Systèmes externes que l'IA peut interroger. Tant qu'aucune URL
    | n'est définie, l'outil correspondant répond « information
    | indisponible » plutôt que d'inventer une réponse.
    |
    | L'URL peut contenir le jeton {reference}, remplacé par la
    | référence fournie par le client.
    |
    */

    'integrations' => [

        'orders' => array_filter([
            'url' => env('AI_ORDERS_URL'),
            'token' => env('AI_ORDERS_TOKEN'),
        ]),
    ],

    /*
    |--------------------------------------------------------------------------
    | Catégories de tickets reconnues par l'IA
    |--------------------------------------------------------------------------
    */

    'categories' => [
        'facturation',
        'livraison',
        'technique',
        'reclamation',
        'remboursement',
        'commande',
        'compte',
        'general',
    ],
];
