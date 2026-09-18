<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */
    'ai' => [
    'provider' => env('AI_PROVIDER', 'openai'),
],
'inbound_email' => [

    /*
     * mailgun | postmark | sendgrid. Détermine le mode de
     * vérification appliqué aux webhooks entrants.
     */
    'provider' => env('INBOUND_EMAIL_PROVIDER', 'postmark'),

    /*
     * Mot de passe de l'authentification HTTP basique, à renseigner
     * dans l'URL du webhook chez le fournisseur.
     */
    'basic_password' => env('INBOUND_EMAIL_PASSWORD'),

    /*
     * Clé de signature Mailgun, onglet Webhooks du tableau de bord.
     */
    'mailgun_key' => env('INBOUND_EMAIL_MAILGUN_KEY'),
],

'twilio' => [
    'auth_token' => env('TWILIO_AUTH_TOKEN'),

    /*
     * URL publique vue par Twilio. À renseigner quand l'application
     * est derrière un proxy ou un tunnel (ngrok), car APP_URL ne
     * correspond alors pas à l'adresse réellement appelée.
     */
    'webhook_base_url' => env('TWILIO_WEBHOOK_BASE_URL'),
],
'widget' => [
    'token' => env('WIDGET_TOKEN'),
],
'openai' => [
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
],

'anthropic' => [
    'api_key' => env('ANTHROPIC_API_KEY'),
    'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
