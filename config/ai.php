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
    | Durée maximale de réflexion à l'écrit
    |--------------------------------------------------------------------------
    |
    | En secondes. Le client ne patiente pas en ligne comme au
    | téléphone, la marge est donc plus large.
    |
    */

    'time_budget' => (float) env('AI_TIME_BUDGET', 60),

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
    | Formules
    |--------------------------------------------------------------------------
    |
    | Chaque entreprise est rattachée à une formule. Les valeurs d'une
    | formule s'appliquent par-dessus les réglages par défaut, et les
    | réglages propres à une entreprise passent encore par-dessus.
    |
    |     valeurs par défaut  <  formule  <  réglages de l'entreprise
    |
    | Ce qui permet d'accorder une exception à un client sans sortir
    | sa formule, ni toucher aux autres.
    |
    | Un plafond négatif signifie « illimité », zéro signifie
    | « interdit ».
    |
    */

    'plans' => [

        /*
         * Découverte.
         *
         * Volontairement sans bloc « quota » : la formule gratuite prend
         * les plafonds définis plus bas, eux-mêmes pilotés par le .env.
         * Ainsi AI_QUOTA_VOICE_CALLS reste la manette utilisable pour
         * ouvrir la voix à tout le monde, notamment pendant tes essais.
         *
         * Ce qui distingue le gratuit, c'est la liste d'outils.
         */
        'free' => [
            'label' => 'Découverte',
            'price' => 0,
            'pitch' => 'De quoi juger sur pièces, sans engagement.',

            'allowed_actions' => [
                'search_knowledge',
                'get_client_profile',
                'get_ticket_status',
                'record_insights',
                'escalate_to_human',
                'create_ticket',
            ],
        ],

        /*
         * Formule d'entrée payante : le chat sans limite, la voix
         * ouverte, et les actions de suivi.
         */
        'pro' => [
            'label' => 'Pro',
            'price' => (int) env('AI_PRICE_PLAN_PRO', 25000),
            'pitch' => 'Le chat sans limite, la voix et le suivi automatique.',

            'level' => 'assist',

            'quota' => [
                'ai_messages' => -1,
                'voice_calls' => 300,
            ],

            'allowed_actions' => [
                'search_knowledge',
                'get_client_profile',
                'get_order_status',
                'get_ticket_status',
                'send_images',
                'record_insights',
                'create_ticket',
                'update_ticket',
                'schedule_follow_up',
                'escalate_to_human',
            ],
        ],

        /*
         * Tout ouvert, y compris le mode autonome.
         */
        'business' => [
            'label' => 'Business',
            'price' => (int) env('AI_PRICE_PLAN_BUSINESS', 75000),
            'pitch' => 'Tout ouvert, y compris l\'assistant en mode autonome.',

            'level' => 'auto',

            'quota' => [
                'ai_messages' => -1,
                'voice_calls' => -1,
            ],

            'allowed_actions' => [
                'search_knowledge',
                'get_client_profile',
                'get_order_status',
                'get_ticket_status',
                'send_images',
                'record_insights',
                'create_ticket',
                'update_ticket',
                'schedule_follow_up',
                'escalate_to_human',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Abonnements
    |--------------------------------------------------------------------------
    |
    | Les prix des formules sont exprimés en unités entières de la
    | devise : le franc CFA n'ayant pas de centimes, 25000 se lit
    | 25 000 FCFA.
    |
    | « manual » encaisse hors ligne — virement, espèces, transfert
    | mobile reçu directement — et le paiement est confirmé à la main
    | depuis la console. C'est le mode par défaut : il permet de
    | vendre avant d'avoir un compte marchand.
    |
    */

    'billing' => [

        'provider' => env('BILLING_PROVIDER', 'manual'),

        'currency' => env('BILLING_CURRENCY', 'XOF'),

        /*
         * Durée d'un abonnement, en jours.
         */
        'period_days' => (int) env('BILLING_PERIOD_DAYS', 30),

        /*
         * Jours de tolérance après l'échéance avant de rebasculer
         * l'entreprise en formule gratuite. Couper le service le jour
         * même d'un retard de paiement fait perdre des clients qui
         * seraient restés.
         */
        'grace_days' => (int) env('BILLING_GRACE_DAYS', 3),

        'cinetpay' => [
            'site_id' => env('CINETPAY_SITE_ID'),
            'api_key' => env('CINETPAY_API_KEY'),
            'secret_key' => env('CINETPAY_SECRET_KEY'),
            'base_url' => env(
                'CINETPAY_BASE_URL',
                'https://api-checkout.cinetpay.com/v2'
            ),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Plafonds de consommation
    |--------------------------------------------------------------------------
    |
    | Chaque message traité par l'IA coûte un appel au modèle, et chaque
    | appel téléphonique coûte des minutes chez l'opérateur. Sans plafond,
    | une offre gratuite qui rencontre du succès devient une facture.
    |
    | Une valeur négative signifie « illimité », zéro signifie
    | « interdit ». Les valeurs peuvent être redéfinies par organisation
    | dans ai_settings.quota, par exemple pour un client payant.
    |
    | Le plafond atteint ne coupe pas le service : l'assistant se met en
    | retrait et les demandes partent vers un agent humain.
    |
    */

    'quota' => [

        'plan' => env('AI_PLAN', 'free'),

        /*
         * Réponses de l'IA par mois.
         */
        'ai_messages' => (int) env('AI_QUOTA_MESSAGES', 100),

        /*
         * Appels vocaux par mois. Zéro par défaut : la voix est de loin
         * le poste le plus coûteux, elle n'a pas sa place dans une offre
         * gratuite.
         */
        'voice_calls' => (int) env('AI_QUOTA_VOICE_CALLS', 0),

        /*
         * Seuil d'alerte du responsable, en part du plafond.
         */
        'warn_at' => (float) env('AI_QUOTA_WARN_AT', 0.8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Coûts unitaires
    |--------------------------------------------------------------------------
    |
    | Sert uniquement à estimer la dépense du mois. À ajuster selon le
    | modèle employé et le tarif de l'opérateur téléphonique.
    |
    */

    'pricing' => [
        'input_per_million' => (float) env('AI_PRICE_INPUT', 3.00),
        'output_per_million' => (float) env('AI_PRICE_OUTPUT', 15.00),
        'voice_per_minute' => (float) env('AI_PRICE_VOICE_MINUTE', 0.02),
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

        /*
         * Durée maximale de réflexion, en secondes.
         *
         * L'opérateur coupe la requête au bout d'une quinzaine de
         * secondes, et le client patiente en ligne pendant ce temps.
         * Passé ce budget, l'IA conclut avec ce qu'elle sait.
         */
        'time_budget' => (float) env('AI_VOICE_TIME_BUDGET', 10),
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
