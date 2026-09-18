<?php

namespace App\Console\Commands;

use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

/**
 * Simule un appel téléphonique complet sans passer par l'opérateur.
 *
 * Sert à vérifier trois choses avant de brancher un vrai numéro :
 *
 * 1. la chaîne fonctionne de bout en bout (accueil, écoute, réponse) ;
 * 2. la signature des webhooks est correctement calculée ;
 * 3. la réponse arrive dans le délai que l'opérateur tolère — c'est le
 *    point qui se voit le moins en développement et qui casse le plus
 *    d'appels en production.
 */
class VoiceCheck extends Command
{
    protected $signature = 'ai:voice-check
                            {--organization= : Identifiant de l\'organisation}
                            {--say=Bonjour, je voudrais connaître vos horaires : ce que dit le client}';

    protected $description = 'Simule un appel entrant et mesure les temps de réponse.';

    public function handle(Kernel $kernel): int
    {
        $organization = $this->resolveOrganization();

        if (!$organization) {
            return self::FAILURE;
        }

        $token = $organization->widget_token;

        $this->line('');
        $this->info('Organisation : ' . $organization->name);
        $this->line('Jeton        : ' . substr($token, 0, 12) . '…');

        $budget = (float) config('ai.voice.time_budget', 10);

        $this->line('Budget       : ' . $budget . ' s par réponse');
        $this->line('');

        /*
         * Étape 1 : l'appel arrive.
         */
        $callSid = 'CA' . strtoupper(bin2hex(random_bytes(8)));

        [$incoming, $elapsed] = $this->hit(
            $kernel,
            "/api/voice/{$token}/incoming",
            [
                'From' => '+2250700000000',
                'CallSid' => $callSid,
            ]
        );

        $this->report('Appel entrant', $incoming, $elapsed, $budget);

        if ($incoming->getStatusCode() !== 200) {
            $this->error(
                "L'appel entrant a échoué. Vérifiez TWILIO_AUTH_TOKEN "
                . "et APP_URL."
            );

            return self::FAILURE;
        }

        /*
         * La conversation créée est nécessaire pour l'étape suivante.
         */
        $conversationId = \App\Models\Call::query()
            ->where('provider_call_id', $callSid)
            ->value('conversation_id');

        if (!$conversationId) {
            $this->error('Aucune conversation créée pour cet appel.');

            return self::FAILURE;
        }

        /*
         * Étape 2 : le client parle. C'est ici que l'IA travaille,
         * donc ici que le temps compte.
         */
        $speech = (string) $this->option('say');

        $this->line('Le client dit : « ' . $speech . ' »');
        $this->line('');

        [$handled, $elapsed] = $this->hit(
            $kernel,
            "/api/voice/{$token}/handle?conversation_id={$conversationId}&attempts=0",
            [
                'SpeechResult' => $speech,
                'CallSid' => $callSid,
                'Confidence' => '0.95',
            ]
        );

        $this->report('Réponse de l\'IA', $handled, $elapsed, $budget);

        /*
         * Étape 3 : fin de l'appel.
         */
        [$status] = $this->hit(
            $kernel,
            "/api/voice/{$token}/status",
            [
                'CallSid' => $callSid,
                'CallStatus' => 'completed',
                'CallDuration' => '42',
            ]
        );

        $this->line(
            'Clôture      : HTTP ' . $status->getStatusCode()
        );

        $this->line('');

        if ($elapsed > $budget) {
            $this->warn(
                'La réponse dépasse le budget. En production, le client '
                . 'entendrait un blanc puis une coupure.'
            );

            $this->line(
                'Pistes : baisser AI_VOICE_MAX_STEPS, réduire le nombre '
                . 'd\'outils autorisés, ou raccourcir vos fiches de '
                . 'connaissances.'
            );
        } else {
            $this->info('Chaîne vocale opérationnelle.');
        }

        return self::SUCCESS;
    }

    /**
     * Envoie une requête signée, comme le ferait l'opérateur.
     */
    private function hit(Kernel $kernel, string $path, array $parameters): array
    {
        $url = rtrim(
            config('services.twilio.webhook_base_url') ?: config('app.url'),
            '/'
        ) . $path;

        $request = Request::create($url, 'POST', $parameters);

        $token = config('services.twilio.auth_token');

        if ($token) {
            $request->headers->set(
                'X-Twilio-Signature',
                $this->signature($url, $parameters, $token)
            );
        }

        $startedAt = microtime(true);

        $response = $kernel->handle($request);

        return [$response, round(microtime(true) - $startedAt, 2)];
    }

    /**
     * Même algorithme que le middleware de vérification.
     */
    private function signature(string $url, array $parameters, string $token): string
    {
        ksort($parameters);

        $payload = $url;

        foreach ($parameters as $key => $value) {
            $payload .= $key . $value;
        }

        return base64_encode(hash_hmac('sha1', $payload, $token, true));
    }

    private function report(
        string $label,
        $response,
        float $elapsed,
        float $budget
    ): void {
        $status = $response->getStatusCode();

        $body = $response->getContent();

        $this->line(
            str_pad($label, 16) . ': HTTP ' . $status
            . '   ' . $elapsed . ' s'
            . ($elapsed > $budget ? '   ⚠ au-delà du budget' : '')
        );

        /*
         * On extrait ce que le client entendrait réellement.
         */
        if (preg_match_all('/<Say[^>]*>(.*?)<\/Say>/s', $body, $matches)) {
            foreach ($matches[1] as $spoken) {
                $this->line('   « ' . html_entity_decode(trim($spoken)) . ' »');
            }
        }

        if (str_contains($body, '<Dial')) {
            $this->line('   [transfert vers un conseiller]');
        }

        if (str_contains($body, '<Gather')) {
            $this->line('   [écoute de la réponse du client]');
        }

        $this->line('');
    }

    private function resolveOrganization(): ?Organization
    {
        if ($this->option('organization')) {
            $organization = Organization::find(
                (int) $this->option('organization')
            );

            if (!$organization) {
                $this->error('Organisation introuvable.');

                return null;
            }

            return $organization;
        }

        $organization = Organization::query()
            ->whereNotNull('widget_token')
            ->orderBy('id')
            ->first();

        if (!$organization) {
            $this->error(
                'Aucune organisation avec un jeton widget. '
                . 'Créez-en une avant de lancer ce diagnostic.'
            );

            return null;
        }

        return $organization;
    }
}
