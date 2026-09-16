<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingMessage;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Réception des emails clients.
 *
 * Compatible avec les webhooks de Postmark, Mailgun et SendGrid :
 * les noms de champs varient d'un service à l'autre, on accepte
 * les variantes les plus courantes.
 *
 * L'email devient un message dans une conversation, puis suit
 * exactement le même chemin que le chat : ProcessIncomingMessage,
 * l'agent, ses outils, l'Autopilot.
 */
class InboundEmailController extends Controller
{
    public function store(Request $request, string $token): JsonResponse
    {
        $organization = Organization::query()
            ->where('widget_token', $token)
            ->first();

        abort_unless($organization, 404);

        $from = $this->extractEmail(
            $request->input('From')
            ?? $request->input('from')
            ?? $request->input('sender')
        );

        if (!$from) {
            return response()->json([
                'message' => 'Expéditeur introuvable.',
            ], 422);
        }

        $subject = trim((string) (
            $request->input('Subject')
            ?? $request->input('subject')
            ?? 'Demande par email'
        ));

        $body = $this->extractBody($request);

        if (!trim($body)) {
            return response()->json([
                'message' => 'Message vide.',
            ], 422);
        }

        $name = $this->extractName(
            $request->input('FromName')
            ?? $request->input('from')
            ?? $request->input('From')
        );

        $result = DB::transaction(function () use (
            $organization,
            $request,
            $from,
            $name,
            $subject,
            $body
        ) {
            $client = $this->resolveClient($organization, $from, $name);

            $conversation = $this->resolveConversation(
                $organization,
                $client,
                $request,
                $subject
            );

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => null,
                'sender_type' => 'client',
                'content' => $body,
                'channel' => 'email',
                'ai_generated' => false,
                'ai_processed' => false,
                'ai_status' => 'pending',
                'metadata' => [
                    'source' => 'email',
                    'from' => $from,
                    'subject' => $subject,
                ],
            ]);

            $conversation->update(['last_message_at' => now()]);

            return [
                'conversation' => $conversation,
                'message' => $message,
            ];
        });

        /*
         * L'IA ne répond que si la conversation lui appartient encore.
         * Si un agent a repris la main, l'email atterrit simplement
         * dans sa file.
         */
        if ($result['conversation']->ai_enabled) {
            ProcessIncomingMessage::dispatch($result['message']->id);
        }

        Log::info(
            'Email entrant traité.',
            [
                'organization_id' => $organization->id,
                'conversation_id' => $result['conversation']->id,
                'from' => $from,
            ]
        );

        return response()->json([
            'conversation_id' => $result['conversation']->id,
            'message_id' => $result['message']->id,
        ], 201);
    }

    /**
     * Rattache l'email à une conversation existante si possible.
     */
    private function resolveConversation(
        Organization $organization,
        Client $client,
        Request $request,
        string $subject
    ): Conversation {
        /*
         * En-tête posé par nos propres réponses : le rattachement
         * est alors certain.
         */
        $headerId = $request->input('X-Conversation-Id')
            ?? data_get($request->input('headers'), 'X-Conversation-Id');

        if ($headerId) {
            $conversation = Conversation::query()
                ->where('organization_id', $organization->id)
                ->where('client_id', $client->id)
                ->find((int) $headerId);

            if ($conversation) {
                return $conversation;
            }
        }

        /*
         * Sinon : une conversation email ouverte avec ce client,
         * active dans les sept derniers jours.
         */
        $recent = Conversation::query()
            ->where('organization_id', $organization->id)
            ->where('client_id', $client->id)
            ->where('channel', 'email')
            ->whereIn('status', ['open', 'pending'])
            ->where('last_message_at', '>=', now()->subDays(7))
            ->latest('last_message_at')
            ->first();

        if ($recent) {
            return $recent;
        }

        return Conversation::create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'subject' => Str::limit(
                preg_replace('/^(re|ref|tr|fwd)\s*:\s*/i', '', $subject),
                180,
                ''
            ) ?: 'Demande par email',
            'channel' => 'email',
            'status' => 'open',
            'priority' => 'normal',
            'ai_enabled' => true,
            'last_message_at' => now(),
        ]);
    }

    private function resolveClient(
        Organization $organization,
        string $email,
        ?string $name
    ): Client {
        $client = Client::query()
            ->where('organization_id', $organization->id)
            ->where('email', $email)
            ->first();

        if ($client) {
            return $client;
        }

        $parts = $name
            ? preg_split('/\s+/', trim($name), 2)
            : [Str::before($email, '@'), ''];

        return Client::create([
            'organization_id' => $organization->id,
            'first_name' => $parts[0] ?: 'Client',
            'last_name' => $parts[1] ?? '',
            'email' => $email,
            'status' => 'active',
            'notes' => 'Créé automatiquement depuis un email entrant.',
        ]);
    }

    /**
     * Corps du message, en privilégiant le texte brut.
     */
    private function extractBody(Request $request): string
    {
        $text = $request->input('TextBody')
            ?? $request->input('text')
            ?? $request->input('body-plain')
            ?? $request->input('plain');

        if ($text) {
            return $this->stripQuotedReply(trim($text));
        }

        $html = $request->input('HtmlBody')
            ?? $request->input('html')
            ?? $request->input('body-html');

        if (!$html) {
            return '';
        }

        $text = preg_replace('/<br\s*\/?>|<\/p>/i', "\n", $html);

        return $this->stripQuotedReply(
            trim(html_entity_decode(strip_tags($text)))
        );
    }

    /**
     * Retire l'historique cité sous la réponse.
     *
     * Sans cela, chaque email renvoie toute la conversation au modèle
     * et fait exploser le contexte.
     */
    private function stripQuotedReply(string $text): string
    {
        $markers = [
            '/^Le .+ a écrit\s*:/mu',
            '/^On .+ wrote\s*:/mu',
            '/^-{2,}\s*Message d\'origine\s*-{2,}/mu',
            '/^_{5,}/mu',
            '/^De\s*:.+$/mu',
        ];

        foreach ($markers as $marker) {
            if (preg_match($marker, $text, $matches, PREG_OFFSET_CAPTURE)) {
                $text = substr($text, 0, $matches[0][1]);
            }
        }

        /*
         * Lignes citées classiques.
         */
        $lines = array_filter(
            explode("\n", $text),
            fn ($line) => !str_starts_with(trim($line), '>')
        );

        return trim(implode("\n", $lines));
    }

    private function extractEmail(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        if (preg_match('/[\w.+-]+@[\w-]+\.[\w.-]+/', $value, $matches)) {
            return Str::lower($matches[0]);
        }

        return null;
    }

    private function extractName(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $name = trim(preg_replace('/<[^>]*>/', '', $value));

        $name = trim($name, " \t\n\r\0\x0B\"'");

        return $name && !str_contains($name, '@') ? $name : null;
    }
}
