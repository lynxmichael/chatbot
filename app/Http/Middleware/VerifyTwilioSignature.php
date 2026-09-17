<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vérifie que la requête vient bien de l'opérateur téléphonique.
 *
 * Sans ce contrôle, n'importe qui connaissant l'URL peut simuler un
 * appel entrant, faire sonner les postes, créer des conversations et
 * consommer des appels au modèle. Le jeton dans l'URL protège des
 * accès accidentels, pas d'un acte volontaire.
 *
 * Twilio signe chaque requête : on recalcule la même signature avec le
 * jeton d'authentification du compte, et on compare.
 *
 * Algorithme : HMAC-SHA1 sur l'URL complète suivie des paramètres POST
 * triés par nom et concaténés, encodé en base64.
 */
class VerifyTwilioSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('services.twilio.auth_token');

        /*
         * Aucun jeton configuré : la vérification est impossible.
         *
         * En local on laisse passer pour permettre les tests, mais en
         * production on refuse — une porte ouverte par défaut est bien
         * plus dangereuse qu'un webhook en panne, qui se remarque.
         */
        if (!$token) {
            if (app()->environment('production')) {
                Log::error(
                    'Webhook vocal refusé : TWILIO_AUTH_TOKEN absent.'
                );

                abort(403, 'Signature verification unavailable.');
            }

            return $next($request);
        }

        $signature = $request->header('X-Twilio-Signature');

        if (!$signature) {
            Log::warning(
                'Webhook vocal sans signature.',
                ['ip' => $request->ip(), 'path' => $request->path()]
            );

            abort(403, 'Missing signature.');
        }

        if (!$this->isValid($request, $token, $signature)) {
            Log::warning(
                'Webhook vocal avec signature invalide.',
                ['ip' => $request->ip(), 'path' => $request->path()]
            );

            abort(403, 'Invalid signature.');
        }

        return $next($request);
    }

    private function isValid(
        Request $request,
        string $token,
        string $signature
    ): bool {
        /*
         * L'URL doit être exactement celle que Twilio a appelée, chaîne
         * de requête comprise. Derrière un proxy ou un tunnel, APP_URL
         * peut différer de ce que voit Laravel : on privilégie donc la
         * valeur configurée quand elle existe.
         */
        $base = rtrim(
            config('services.twilio.webhook_base_url') ?: config('app.url'),
            '/'
        );

        $url = $base . '/' . ltrim($request->getRequestUri(), '/');

        $payload = $url;

        $parameters = $request->post();

        ksort($parameters);

        foreach ($parameters as $key => $value) {
            $payload .= $key . (is_array($value) ? implode('', $value) : $value);
        }

        $expected = base64_encode(
            hash_hmac('sha1', $payload, $token, true)
        );

        /*
         * Comparaison à temps constant : une comparaison ordinaire
         * laisse fuir la signature attendue, caractère par caractère.
         */
        return hash_equals($expected, $signature);
    }
}
