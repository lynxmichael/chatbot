<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WidgetCallController extends Controller
{
    /**
     * Démarrer un appel depuis le widget.
     */
    public function start(Request $request): JsonResponse
    {
        $token = $request->header('X-Widget-Token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token widget manquant.',
            ], 401);
        }

        $organization = Organization::where('widget_token', $token)->first();

        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Token widget invalide.',
            ], 403);
        }

        $validated = $request->validate([
            'conversation_id' => ['nullable', 'integer'],
            'client_id' => ['nullable', 'integer'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        /*
         * ============================================================
         * 1. Récupérer la conversation et le client
         * ============================================================
         */
        $conversation = null;
        $client = null;

        if (!empty($validated['conversation_id'])) {
            $conversation = Conversation::query()
                ->where('organization_id', $organization->id)
                ->where('id', $validated['conversation_id'])
                ->first();

            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversation introuvable.',
                ], 404);
            }

            if ($conversation->client_id) {
                $client = Client::query()
                    ->where('organization_id', $organization->id)
                    ->where('id', $conversation->client_id)
                    ->first();
            }

            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client associé à la conversation introuvable.',
                ], 404);
            }
        }

        /*
         * ============================================================
         * 2. Créer/récupérer le client et la conversation
         * ============================================================
         */
        if (!$conversation) {
            if (!empty($validated['client_id'])) {
                $client = Client::query()
                    ->where('organization_id', $organization->id)
                    ->where('id', $validated['client_id'])
                    ->first();

                if (!$client) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Client introuvable.',
                    ], 404);
                }
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

            if (!$client) {
                $client = Client::create([
                    'organization_id' => $organization->id,
                    'first_name' => $validated['first_name'] ?? 'Client',
                    'last_name' => $validated['last_name'] ?? 'Widget',
                    'email' => $validated['email'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'status' => 'active',
                ]);
            }

            $conversation = Conversation::create([
                'organization_id' => $organization->id,
                'client_id' => $client->id,
                'subject' => 'Appel depuis le widget',
                'channel' => 'phone',
                'status' => 'open',
                'priority' => 'normal',
                'ai_enabled' => false,
                'last_message_at' => now(),
            ]);
        }

        /*
         * ============================================================
         * 3. Vérifier si un appel est déjà actif pour cette
         *    conversation.
         *
         *    Un appel "ringing" ou "answered" est considéré actif.
         * ============================================================
         */
        $existingCall = Call::query()
            ->where('organization_id', $organization->id)
            ->where('conversation_id', $conversation->id)
            ->whereIn('status', ['ringing', 'answered'])
            ->whereNull('ended_at')
            ->latest('id')
            ->first();

        if ($existingCall) {
            $agent = $existingCall->user;

            return response()->json([
                'success' => true,
                'call_id' => $existingCall->id,
                'conversation_id' => $conversation->id,
                'status' => $existingCall->status,
                'agent' => $agent ? [
                    'id' => $agent->id,
                    'name' => $agent->name,
                ] : null,
                'message' => $existingCall->status === 'ringing'
                    ? 'Un appel est déjà en attente de prise en charge.'
                    : 'Un appel est déjà en cours.',
            ]);
        }

        /*
         * ============================================================
         * 4. Trouver un agent disponible.
         *
         * Les appels "ringing" et "answered" réservent l'agent.
         * ============================================================
         */
        $busyAgentIds = Call::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', ['ringing', 'answered'])
            ->whereNull('ended_at')
            ->whereNotNull('user_id')
            ->pluck('user_id');

        $agent = User::query()
            ->where('organization_id', $organization->id)
            ->where('role', 'agent')
            ->where('is_active', true)
            ->whereNotIn('id', $busyAgentIds)
            ->orderBy('id')
            ->first();

        if (!$agent) {
            return response()->json([
                'success' => false,
                'status' => 'busy',
                'conversation_id' => $conversation->id,
                'message' => 'Tous les agents sont actuellement occupés.',
            ], 409);
        }

        /*
         * ============================================================
         * 5. Créer l'appel avec le statut "ringing".
         * ============================================================
         */
        $call = DB::transaction(function () use (
            $organization,
            $client,
            $conversation,
            $agent
        ) {
            $call = Call::create([
                'organization_id' => $organization->id,
                'client_id' => $client->id,
                'conversation_id' => $conversation->id,
                'user_id' => $agent->id,
                'type' => 'incoming',

                // L'appel attend maintenant que l'agent le prenne.
                'status' => 'ringing',

                'phone' => $client->phone,
                'duration' => 0,
                'reason' => 'Appel depuis le widget',
                'notes' => null,

                // Le vrai démarrage sera enregistré lorsque
                // l'agent répondra.
                'started_at' => null,
                'ended_at' => null,
            ]);

            $conversation->update([
                'status' => 'open',
                'assigned_to' => $agent->id,
                'last_message_at' => now(),
            ]);

            return $call;
        });

        return response()->json([
            'success' => true,
            'call_id' => $call->id,
            'conversation_id' => $conversation->id,
            'status' => $call->status,
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
            ],
            'message' => 'Appel en attente de prise en charge par un agent.',
        ]);
    }

    /**
     * Répondre à un appel.
     */
    public function answer(Request $request, Call $call): JsonResponse
    {
        $token = $request->header('X-Widget-Token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token widget manquant.',
            ], 401);
        }

        $organization = Organization::where('widget_token', $token)->first();

        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Token widget invalide.',
            ], 403);
        }

        if ($call->organization_id !== $organization->id) {
            return response()->json([
                'success' => false,
                'message' => 'Appel non autorisé.',
            ], 403);
        }

        if ($call->status === 'answered') {
            return response()->json([
                'success' => true,
                'call_id' => $call->id,
                'status' => 'answered',
                'message' => 'L’appel est déjà en cours.',
            ]);
        }

        if ($call->status !== 'ringing') {
            return response()->json([
                'success' => false,
                'call_id' => $call->id,
                'status' => $call->status,
                'message' => 'Cet appel ne peut plus être pris en charge.',
            ], 409);
        }

        $call->update([
            'status' => 'answered',
            'started_at' => now(),
        ]);

        if ($call->conversation) {
            $call->conversation->update([
                'status' => 'open',
                'assigned_to' => $call->user_id,
                'last_message_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'call_id' => $call->id,
            'conversation_id' => $call->conversation_id,
            'status' => 'answered',
            'agent' => $call->user ? [
                'id' => $call->user->id,
                'name' => $call->user->name,
            ] : null,
            'started_at' => $call->started_at,
            'message' => 'Appel accepté.',
        ]);
    }

    /**
     * Récupérer l'état d'un appel.
     */
    public function status(Request $request, Call $call): JsonResponse
    {
        $token = $request->header('X-Widget-Token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token widget manquant.',
            ], 401);
        }

        $organization = Organization::where('widget_token', $token)->first();

        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Token widget invalide.',
            ], 403);
        }

        if ($call->organization_id !== $organization->id) {
            return response()->json([
                'success' => false,
                'message' => 'Appel non autorisé.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'call_id' => $call->id,
            'status' => $call->status,
            'conversation_id' => $call->conversation_id,
            'agent' => $call->user ? [
                'id' => $call->user->id,
                'name' => $call->user->name,
            ] : null,
            'started_at' => $call->started_at,
            'ended_at' => $call->ended_at,
            'duration' => $call->duration,
        ]);
    }

    /**
     * Terminer un appel depuis le widget.
     */
    public function end(Request $request, Call $call): JsonResponse
    {
        $token = $request->header('X-Widget-Token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token widget manquant.',
            ], 401);
        }

        $organization = Organization::where('widget_token', $token)->first();

        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Token widget invalide.',
            ], 403);
        }

        if ($call->organization_id !== $organization->id) {
            return response()->json([
                'success' => false,
                'message' => 'Appel non autorisé.',
            ], 403);
        }

        if (in_array($call->status, ['cancelled', 'missed', 'busy', 'failed'], true)) {
            return response()->json([
                'success' => true,
                'call_id' => $call->id,
                'status' => $call->status,
                'message' => 'L’appel est déjà terminé.',
            ]);
        }

        $duration = 0;

        if ($call->status === 'answered' && $call->started_at) {
            $duration = max(
                0,
                now()->diffInSeconds($call->started_at)
            );
        }

        $call->update([
            'status' => 'cancelled',
            'duration' => $duration,
            'ended_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'call_id' => $call->id,
            'status' => 'cancelled',
            'duration' => $duration,
            'message' => 'Appel terminé.',
        ]);
    }
}
