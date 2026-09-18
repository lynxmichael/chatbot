<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentifie les webhooks d'email entrant.
 *
 * Les trois fournisseurs courants s'y prennent différemment :
 *
 * - Mailgun signe chaque requête (HMAC-SHA256 sur timestamp + token) ;
 * - Postmark ne signe pas : il propose une authentification HTTP basique ;
 * - SendGrid signe en ECDSA, ou laisse l'URL secrète faire le travail.
 *
 * On accepte donc trois preuves, dans cet ordre. À défaut, le jeton de
 * l'organisation dans l'URL fait office de secret partagé — suffisant
 * contre un accès accidentel, insuffisant contre quelqu'un de décidé,
 * d'où l'avertissement en production.
 */
class VerifyInboundEmail
{
    public function handle(Request $request, Closure $next): Response
    {
        $provider = config('services.inbound_email.provider');

        if ($provider === 'mailgun' && $this->hasMailgunSignature($request)) {
            return $this->verifyMailgun($request, $next);
        }

        $secret = config('services.inbound_email.basic_password');

        if ($secret) {
            /*
             * Comparaison à temps constant : une comparaison ordinaire
             * laisse fuir le secret caractère par caractère.
             */
            if (!hash_equals($secret, (string) $request->getPassword())) {
                Log::warning(
                    'Webhook email refusé : authentification invalide.',
                    ['ip' => $request->ip()]
                );

                abort(403, 'Invalid credentials.');
            }

            return $next($request);
        }

        if (app()->environment('production')) {
            Log::notice(
                "Webhook email accepté sans vérification : aucun secret "
                . "configuré. Renseignez INBOUND_EMAIL_PASSWORD.",
                ['ip' => $request->ip()]
            );
        }

        return $next($request);
    }

    private function hasMailgunSignature(Request $request): bool
    {
        return $request->filled('signature')
            || $request->filled('signature.signature');
    }

    private function verifyMailgun(Request $request, Closure $next): Response
    {
        $key = config('services.inbound_email.mailgun_key');

        if (!$key) {
            abort(403, 'Mailgun signing key missing.');
        }

        /*
         * Mailgun place ces champs à plat ou dans un objet
         * « signature » selon la version de l'API.
         */
        $timestamp = $request->input('timestamp')
            ?? $request->input('signature.timestamp');

        $token = $request->input('token')
            ?? $request->input('signature.token');

        $signature = $request->input('signature')
            ?? $request->input('signature.signature');

        if (is_array($signature)) {
            $signature = $signature['signature'] ?? null;
        }

        if (!$timestamp || !$token || !$signature) {
            abort(403, 'Incomplete Mailgun signature.');
        }

        /*
         * Une signature vieille de plus de cinq minutes est rejetée :
         * sans cela, une requête interceptée serait rejouable
         * indéfiniment.
         */
        if (abs(time() - (int) $timestamp) > 300) {
            Log::warning('Webhook email refusé : signature expirée.');

            abort(403, 'Expired signature.');
        }

        $expected = hash_hmac('sha256', $timestamp . $token, $key);

        if (!hash_equals($expected, (string) $signature)) {
            Log::warning(
                'Webhook email refusé : signature Mailgun invalide.',
                ['ip' => $request->ip()]
            );

            abort(403, 'Invalid signature.');
        }

        return $next($request);
    }
}
