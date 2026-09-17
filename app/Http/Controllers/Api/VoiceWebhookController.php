<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use App\Services\AI\AgentRunner;
use App\Services\AI\Autopilot\AutopilotPolicy;
use App\Services\AI\Tools\ToolContext;
use App\Services\AI\Usage\UsageMeter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Accueil téléphonique par l'IA.
 *
 * Le flux suit la mécanique TwiML de Twilio :
 *
 *   appel entrant  -> /incoming  : message d'accueil + écoute
 *   parole du client -> /handle  : réponse de l'IA + nouvelle écoute
 *   fin de l'appel -> /status    : durée et clôture
 *
 * L'IA utilise exactement le même AgentRunner que le chat : mêmes outils,
 * même base de connaissances, mêmes règles d'Autopilot. Seul le prompt
 * est adapté à l'oral.
 *
 * Contrainte importante : l'opérateur attend la réponse HTTP pendant
 * l'appel. La boucle d'agent est donc volontairement raccourcie ici
 * (config ai.voice.max_steps), et le traitement reste synchrone.
 */
class VoiceWebhookController extends Controller
{
    public function __construct(
        private readonly AgentRunner $agent
    ) {
    }

    /**
     * Appel entrant.
     */
    public function incoming(
        Request $request,
        string $token,
        UsageMeter $usage
    ): Response {
        $organization = $this->organizationFromToken($token);

        $from = $this->normalizePhone($request->input('From'));

        $callSid = $request->input('CallSid');

        $client = $this->resolveClient($organization, $from);

        $conversation = Conversation::create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'subject' => 'Appel téléphonique',
            'channel' => 'phone',
            'status' => 'open',
            'priority' => 'normal',
            'ai_enabled' => true,
            'last_message_at' => now(),
        ]);

        Call::create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'conversation_id' => $conversation->id,
            'user_id' => null,
            'type' => 'incoming',
            'status' => 'answered',
            'ai_handled' => true,
            'provider' => 'twilio',
            'provider_call_id' => $callSid,
            'phone' => $from ?: 'inconnu',
            'duration' => 0,
            'reason' => 'Appel pris en charge par l\'assistant',
            'started_at' => now(),
        ]);

        $policy = AutopilotPolicy::forOrganization($organization);

        /*
         * Plafond vocal atteint, ou Autopilot coupé : on ne fait pas
         * semblant, on bascule immédiatement vers un humain.
         *
         * La vérification précède le comptage : un appel refusé ne doit
         * pas consommer le plafond.
         */
        if (
            $policy->isDisabled()
            || $policy->isDraftOnly()
            || !$usage->allows($organization, 'voice_calls')
        ) {
            return $this->twiml(
                $this->say(
                    "Bonjour, je vous mets en relation avec un conseiller."
                )
                . $this->dial($organization)
            );
        }

        $usage->record($organization, ['voice_calls' => 1]);

        $greeting = $policy->persona()
            ? 'Bonjour, ' . $policy->persona() . '. Que puis-je faire pour vous ?'
            : 'Bonjour, vous êtes au service client de '
                . ($policy->businessName() ?: $organization->name)
                . '. Que puis-je faire pour vous ?';

        return $this->twiml(
            $this->gather(
                $token,
                $conversation->id,
                $this->say($greeting)
            )
            . $this->say("Je n'ai rien entendu. Au revoir.")
        );
    }

    /**
     * Parole du client transcrite par l'opérateur.
     */
    public function handle(Request $request, string $token): Response
    {
        $organization = $this->organizationFromToken($token);

        $conversationId = (int) $request->input('conversation_id');

        $conversation = Conversation::query()
            ->where('organization_id', $organization->id)
            ->with('client')
            ->find($conversationId);

        if (!$conversation) {
            return $this->twiml(
                $this->say('Une erreur technique est survenue. Au revoir.')
            );
        }

        $speech = trim((string) $request->input('SpeechResult'));

        /*
         * Rien de compris : on redonne la parole une fois,
         * puis on passe à un humain.
         */
        if ($speech === '') {
            $attempts = (int) $request->input('attempts', 0) + 1;

            if ($attempts >= 3) {
                return $this->transferToHuman(
                    $organization,
                    $conversation,
                    'Je vous passe un conseiller.'
                );
            }

            return $this->twiml(
                $this->gather(
                    $token,
                    $conversation->id,
                    $this->say("Je n'ai pas bien entendu. Pouvez-vous répéter ?"),
                    $attempts
                )
                . $this->say('Je vous passe un conseiller.')
                . $this->dial($organization)
            );
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => null,
            'sender_type' => 'client',
            'content' => $speech,
            'channel' => 'phone',
            'ai_generated' => false,
            'ai_processed' => false,
            'ai_status' => 'processing',
            'metadata' => [
                'source' => 'voice',
                'confidence' => $request->input('Confidence'),
            ],
        ]);

        $this->appendTranscript($organization, $request->input('CallSid'), 'client', $speech);

        try {
            $result = $this->agent->forConversation($conversation, $message);
        } catch (Throwable $exception) {
            Log::error(
                "Échec du traitement vocal.",
                [
                    'conversation_id' => $conversation->id,
                    'error' => $exception->getMessage(),
                ]
            );

            $message->update([
                'ai_status' => 'failed',
                'ai_error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);

            return $this->transferToHuman(
                $organization,
                $conversation,
                'Je rencontre un problème technique. Je vous passe un conseiller.'
            );
        }

        $reply = $result->reply
            ?: 'Je vous passe un conseiller.';

        Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => null,
            'sender_type' => 'ai',
            'content' => $reply,
            'channel' => 'phone',
            'ai_generated' => true,
            'ai_processed' => true,
            'ai_status' => 'completed',
            'metadata' => array_merge(['source' => 'voice'], $result->toMetadata()),
        ]);

        $message->update([
            'ai_processed' => true,
            'ai_status' => 'completed',
        ]);

        $conversation->update(['last_message_at' => now()]);

        $this->appendTranscript($organization, $request->input('CallSid'), 'ai', $reply);

        /*
         * L'IA a décidé de passer la main.
         *
         * Si personne ne peut décrocher — service fermé, ou tous les
         * conseillers occupés — composer le numéro ferait sonner dans le
         * vide puis raccrocherait au nez du client. On lui annonce le
         * rappel à la place : l'outil d'escalade l'a déjà programmé.
         */
        if ($result->escalated) {
            $deferred = $result->effects['deferred'] ?? false;

            if ($deferred) {
                return $this->twiml(
                    $this->say($reply)
                    . $this->say('Merci de votre appel. Au revoir.')
                );
            }

            return $this->twiml(
                $this->say($reply)
                . $this->say('Je vous mets en relation, ne quittez pas.')
                . $this->dial($organization)
            );
        }

        return $this->twiml(
            $this->gather(
                $token,
                $conversation->id,
                $this->say($reply)
            )
            . $this->say('Merci de votre appel. Au revoir.')
        );
    }

    /**
     * Fin de l'appel.
     */
    public function status(
        Request $request,
        string $token,
        UsageMeter $usage
    ): Response {
        $organization = $this->organizationFromToken($token);

        $call = Call::query()
            ->where('organization_id', $organization->id)
            ->where('provider_call_id', $request->input('CallSid'))
            ->first();

        if ($call) {
            $seconds = (int) $request->input('CallDuration', 0);

            if ($seconds > 0) {
                $usage->record($organization, ['voice_seconds' => $seconds]);
            }

            $call->update([
                'duration' => $seconds,
                'ended_at' => now(),
                'status' => match ($request->input('CallStatus')) {
                    'completed' => 'answered',
                    'busy' => 'busy',
                    'no-answer' => 'missed',
                    'failed' => 'failed',
                    'canceled' => 'cancelled',
                    default => $call->status,
                },
            ]);

            /*
             * L'appel est terminé : l'IA ne doit plus écrire
             * dans cette conversation.
             */
            $call->conversation?->update([
                'status' => 'pending',
                'ai_enabled' => false,
            ]);
        }

        return response('', 204);
    }

    /* ------------------------------------------------------------------
     | Outils internes
     |------------------------------------------------------------------ */

    private function transferToHuman(
        Organization $organization,
        Conversation $conversation,
        string $announcement
    ): Response {
        $conversation->update([
            'status' => 'open',
            'ai_enabled' => false,
        ]);

        /*
         * Même règle qu'ailleurs : hors des heures ou sans conseiller
         * libre, on annonce un rappel plutôt que de faire sonner dans
         * le vide.
         */
        $hours = app(\App\Services\AI\Support\BusinessHours::class);

        if (!$hours->hasReachableAgent($organization)) {
            return $this->twiml(
                $this->say(
                    "Nos conseillers ne sont pas joignables pour le moment. "
                    . "Votre demande est enregistrée, nous vous rappelons "
                    . $hours->nextOpeningInWords($organization) . "."
                )
                . $this->say('Merci de votre appel. Au revoir.')
            );
        }

        return $this->twiml(
            $this->say($announcement) . $this->dial($organization)
        );
    }

    private function organizationFromToken(string $token): Organization
    {
        $organization = Organization::query()
            ->where('widget_token', $token)
            ->first();

        abort_unless($organization, 404);

        return $organization;
    }

    private function resolveClient(Organization $organization, ?string $phone): Client
    {
        $client = $phone
            ? Client::query()
                ->where('organization_id', $organization->id)
                ->where('phone', $phone)
                ->first()
            : null;

        if ($client) {
            return $client;
        }

        return Client::create([
            'organization_id' => $organization->id,
            'first_name' => 'Appelant',
            'last_name' => $phone ?: 'inconnu',
            'phone' => $phone,
            'status' => 'active',
            'notes' => 'Créé automatiquement lors d\'un appel entrant.',
        ]);
    }

    private function appendTranscript(
        Organization $organization,
        ?string $callSid,
        string $speaker,
        string $text
    ): void {
        if (!$callSid) {
            return;
        }

        $call = Call::query()
            ->where('organization_id', $organization->id)
            ->where('provider_call_id', $callSid)
            ->first();

        if (!$call) {
            return;
        }

        $transcript = is_array($call->transcript) ? $call->transcript : [];

        $transcript[] = [
            'speaker' => $speaker,
            'text' => $text,
            'at' => now()->toDateTimeString(),
        ];

        $call->update(['transcript' => $transcript]);
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        return preg_replace('/[^\d+]/', '', $phone) ?: null;
    }

    /* ------------------------------------------------------------------
     | Génération TwiML
     |------------------------------------------------------------------ */

    private function twiml(string $body): Response
    {
        return response(
            '<?xml version="1.0" encoding="UTF-8"?><Response>' . $body . '</Response>',
            200,
            ['Content-Type' => 'text/xml; charset=UTF-8']
        );
    }

    private function say(string $text): string
    {
        $voice = config('ai.voice.tts_voice', 'Google.fr-FR-Standard-A');

        $language = config('ai.voice.language', 'fr-FR');

        return '<Say voice="' . e($voice) . '" language="' . e($language) . '">'
            . e($this->forSpeech($text))
            . '</Say>';
    }

    private function gather(
        string $token,
        int $conversationId,
        string $inner,
        int $attempts = 0
    ): string {
        $action = url(
            '/api/voice/' . $token . '/handle?conversation_id=' . $conversationId
            . '&attempts=' . $attempts
        );

        return '<Gather input="speech"'
            . ' language="' . e(config('ai.voice.language', 'fr-FR')) . '"'
            . ' speechTimeout="auto"'
            . ' action="' . e($action) . '"'
            . ' method="POST">'
            . $inner
            . '</Gather>';
    }

    private function dial(Organization $organization): string
    {
        $number = config('ai.voice.fallback_number')
            ?: $organization->phone;

        if (!$number) {
            return $this->say(
                "Aucun conseiller n'est joignable pour le moment. "
                . "Nous vous rappellerons. Au revoir."
            );
        }

        return '<Dial timeout="25">' . e($number) . '</Dial>';
    }

    /**
     * Nettoie le texte avant lecture à voix haute.
     */
    private function forSpeech(string $text): string
    {
        $text = preg_replace('/[*_#`>|]/u', '', $text);

        $text = preg_replace('/\s*[-•]\s+/u', '. ', $text);

        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }
}
