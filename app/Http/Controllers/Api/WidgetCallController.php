<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Organization;
use App\Models\User;
use App\Services\AI\Usage\UsageMeter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Appels lancés depuis le widget.
 *
 * Déroulé réel :
 *
 *   client clique  -> ringing   (le poste de l'agent sonne)
 *   agent décroche -> answered  (la durée démarre ici)
 *   l'un des deux  -> completed
 *   personne       -> missed    (après expiration de la sonnerie)
 *   client renonce -> cancelled
 */
class WidgetCallController extends Controller
{
    /**
     * Durée maximale de sonnerie, en secondes.
     */
    private const RING_TIMEOUT = 45;

    public function start(Request $request, UsageMeter $usage): JsonResponse
    {
        $organization = $this->organizationFromToken($request);

        /*
         * La voix est le poste le plus coûteux : son plafond est vérifié
         * avant même de chercher un agent.
         */
        if (!$usage->allows($organization, 'voice_calls')) {
            return response()->json([
                'success' => false,
                'status' => 'unavailable',
                'message' => "L'appel n'est pas disponible pour le moment. "
                    . "Écrivez-nous, nous vous répondons tout de suite.",
            ], 200);
        }

        $validated = $request->validate([
            'conversation_id' => ['nullable', 'integer'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $result = DB::transaction(function () use ($organization, $validated) {
            $conversation = null;

            $client = null;

            /*
             * Conversation existante : le client en découle directement.
             */
            if (!empty($validated['conversation_id'])) {
                $conversation = Conversation::query()
                    ->where('organization_id', $organization->id)
                    ->find($validated['conversation_id']);

                abort_unless($conversation, 404);

                $client = Client::query()
                    ->where('organization_id', $organization->id)
                    ->find($conversation->client_id);
            }

            if (!$client && !empty($validated['email'])) {
                $client = Client::query()
                    ->where('organization_id', $organization->id)
                    ->where('email', $validated['email'])
                    ->first();
            }

            if (!$client && !empty($validated['phone'])) {
                $client = Client::query()
                    ->where('organization_id', $organization->id)
                    ->where('phone', $validated['phone'])
                    ->first();
            }

            if ($client) {
                /*
                 * On complète le dossier sans jamais écraser
                 * une valeur existante par du vide.
                 */
                $client->update(array_filter([
                    'first_name' => $validated['first_name'] ?? null,
                    'last_name' => $validated['last_name'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                ]));
            } else {
                $client = Client::create([
                    'organization_id' => $organization->id,
                    'first_name' => $validated['first_name'] ?? 'Client',
                    'last_name' => $validated['last_name'] ?? 'Widget',
                    'email' => $validated['email'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'status' => 'active',
                ]);
            }

            if (!$conversation) {
                $conversation = Conversation::create([
                    'organization_id' => $organization->id,
                    'client_id' => $client->id,
                    'assigned_to' => null,
                    'subject' => 'Appel depuis le widget',
                    'channel' => 'web',
                    'status' => 'open',
                    'priority' => 'normal',
                    'ai_enabled' => true,
                    'last_message_at' => now(),
                ]);
            } else {
                $conversation->update(['last_message_at' => now()]);
            }

            /*
             * Un appel est déjà en cours pour cette conversation :
             * on le renvoie au lieu d'en créer un second.
             */
            $existing = Call::query()
                ->where('organization_id', $organization->id)
                ->where('conversation_id', $conversation->id)
                ->whereIn('status', ['ringing', 'answered'])
                ->latest('id')
                ->first();

            if ($existing) {
                return [
                    'call' => $existing,
                    'conversation' => $conversation,
                    'client' => $client,
                    'agent' => $existing->user,
                    'reused' => true,
                ];
            }

            /*
             * Agents joignables : actifs, disponibles, et dont le poste
             * n'est ni en ligne ni déjà en train de sonner.
             */
            $occupied = Call::query()
                ->where('organization_id', $organization->id)
                ->whereIn('status', ['ringing', 'answered'])
                ->whereNotNull('user_id')
                ->pluck('user_id');

            $agent = User::query()
                ->where('organization_id', $organization->id)
                ->whereIn('role', ['agent', 'owner'])
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->where('is_available', true)
                        ->orWhereNull('is_available');
                })
                ->when(
                    $occupied->isNotEmpty(),
                    fn ($query) => $query->whereNotIn('id', $occupied)
                )
                ->orderBy('id')
                ->first();

            $call = Call::create([
                'organization_id' => $organization->id,
                'client_id' => $client->id,
                'conversation_id' => $conversation->id,
                'user_id' => $agent?->id,
                'type' => 'incoming',

                /*
                 * Sans agent joignable, inutile de faire patienter
                 * le client sur une sonnerie qui n'aboutira pas.
                 */
                'status' => $agent ? 'ringing' : 'busy',

                'phone' => $client->phone ?: 'widget',
                'duration' => 0,
                'reason' => 'Appel initié depuis le widget',
                'started_at' => now(),
            ]);

            return [
                'call' => $call,
                'conversation' => $conversation,
                'client' => $client,
                'agent' => $agent,
                'reused' => false,
            ];
        });

        $call = $result['call'];

        if (!$result['reused'] && $call->status === 'ringing') {
            $usage->record($organization, ['voice_calls' => 1]);
        }

        return response()->json([
            'success' => true,
            'call_id' => $call->id,
            'conversation_id' => $result['conversation']->id,
            'status' => $call->status,
            'ring_timeout' => self::RING_TIMEOUT,

            'agent' => $result['agent']
                ? [
                    'id' => $result['agent']->id,
                    'name' => $result['agent']->name,
                ]
                : null,

            'message' => match ($call->status) {
                'ringing' => 'Le poste du conseiller sonne.',
                'answered' => 'Appel en cours.',
                default => 'Tous nos conseillers sont occupés.',
            },
        ], $result['reused'] ? 200 : 201);
    }

    /**
     * Le client raccroche.
     */
    public function end(
        Request $request,
        Call $call,
        UsageMeter $usage
    ): JsonResponse {
        $organization = $this->organizationFromToken($request);

        $this->authorizeCall($organization, $call);

        if (!in_array($call->status, ['ringing', 'answered'], true)) {
            return response()->json([
                'success' => true,
                'call_id' => $call->id,
                'status' => $call->status,
                'duration' => (int) $call->duration,
            ]);
        }

        /*
         * Raccrocher pendant la sonnerie n'est pas un appel terminé,
         * c'est un appel abandonné. La distinction compte pour les
         * statistiques du service.
         */
        $wasAnswered = $call->status === 'answered';

        $duration = $this->duration($call);

        $call->update([
            'status' => $wasAnswered ? 'completed' : 'cancelled',
            'duration' => $duration,
            'ended_at' => now(),
        ]);

        if ($wasAnswered && $duration > 0) {
            $usage->record($organization, ['voice_seconds' => $duration]);
        }

        return response()->json([
            'success' => true,
            'call_id' => $call->id,
            'status' => $call->status,
            'duration' => (int) $call->duration,
        ]);
    }

    /**
     * État de l'appel, interrogé en boucle par le widget.
     */
    public function status(Request $request, Call $call): JsonResponse
    {
        $organization = $this->organizationFromToken($request);

        $this->authorizeCall($organization, $call);

        /*
         * Sonnerie trop longue : l'appel bascule en manqué.
         *
         * Ce basculement se fait ici plutôt que dans une tâche
         * planifiée, parce que le widget interroge cet endpoint
         * toutes les deux secondes pendant la sonnerie.
         */
        if (
            $call->status === 'ringing'
            && $call->started_at
            && $call->started_at->diffInSeconds(now()) > self::RING_TIMEOUT
        ) {
            $call->update([
                'status' => 'missed',
                'ended_at' => now(),
            ]);
        }

        $call->load([
            'client:id,first_name,last_name,email,phone',
            'conversation:id,subject,status,ai_enabled',
            'user:id,name',
        ]);

        return response()->json([
            'success' => true,

            'call' => [
                'id' => $call->id,
                'status' => $call->status,
                'type' => $call->type,
                'duration' => $call->status === 'answered'
                    ? $this->duration($call)
                    : (int) $call->duration,
                'started_at' => $call->started_at,
                'answered_at' => $call->answered_at,
                'ended_at' => $call->ended_at,
            ],

            'agent' => $call->user
                ? [
                    'id' => $call->user->id,
                    'name' => $call->user->name,
                ]
                : null,

            'conversation' => $call->conversation
                ? [
                    'id' => $call->conversation->id,
                    'subject' => $call->conversation->subject,
                    'ai_enabled' => (bool) $call->conversation->ai_enabled,
                ]
                : null,
        ]);
    }

    /**
     * Durée écoulée depuis le décroché.
     */
    private function duration(Call $call): int
    {
        if (!$call->answered_at) {
            return (int) $call->duration;
        }

        return (int) max(0, $call->answered_at->diffInSeconds(now()));
    }

    private function authorizeCall(Organization $organization, Call $call): void
    {
        abort_unless($call->organization_id === $organization->id, 404);
    }

    private function organizationFromToken(Request $request): Organization
    {
        $token = $request->header('X-Widget-Token');

        abort_unless($token, 401);

        $organization = Organization::query()
            ->where('widget_token', $token)
            ->first();

        abort_unless($organization, 401);

        return $organization;
    }
}
