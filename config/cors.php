<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */'paths' => [
    'api/*',
    'sanctum/csrf-cookie',
],

'allowed_methods' => [
    'GET',
    'POST',
    'PUT',
    'PATCH',
    'DELETE',
    'OPTIONS',
],

/*
 * Le widget est conçu pour être posé sur le site de n'importe quel
 * client : la liste blanche d'origines n'a donc pas de sens ici.
 *
 * C'est sans risque dans cette configuration : aucun cookie de session
 * n'est échangé (supports_credentials reste à false) et chaque appel
 * doit présenter le jeton de l'organisation.
 *
 * L'ancienne liste ne contenait ni localhost:8000 ni 127.0.0.1:8000,
 * ce qui bloquait les appels de la page de test elle-même.
 */

'allowed_origins' => ['*'],

'allowed_headers' => [
    'Accept',
    'Content-Type',
    'X-Requested-With',
    'X-Widget-Token',
],

'supports_credentials' => false,
];
