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

class WidgetController extends Controller
{
    public function config(Request $request): JsonResponse
    {
        $organization = $this->organizationFromToken($request);

        return response()->json([
            'success' => true,
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
        ]);
    }

    public function createConversation(Request $request): JsonResponse
    {
        $organization = $this->organizationFromToken($request);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['required', 'string', 'max:10000'],
        ]);

        $conversation = DB::transaction(function () use (
            $organization,
            $validated
        ) {
            $client = Client::firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'email' => $validated['email'] ?? null,
                ],
                [
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'phone' => $validated['phone'] ?? null,
                    'status' => 'active',
                ]
            );

            $client->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'phone' => $validated['phone'] ?? $client->phone,
            ]);

            $conversation = Conversation::create([
                'organization_id' => $organization->id,
                'client_id' => $client->id,
                'assigned_to' => null,
                'subject' => 'Conversation depuis le widget',
                'channel' => 'web',
                'status' => 'open',
                'priority' => 'normal',
                'ai_enabled' => true,
                'last_message_at' => now(),
            ]);

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => null,
                'sender_type' => 'client',
                'content' => $validated['message'],
                'channel' => 'web',
                'ai_generated' => false,
                'ai_processed' => false,
                'ai_status' => 'pending',
                'ai_error' => null,
            ]);

            ProcessIncomingMessage::dispatch(
                $message->id
            );

            return $conversation;
        });

        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->id,
            'message' => 'Conversation créée.',
        ], 201);
    }

    public function sendMessage(
        Request $request,
        Conversation $conversation
    ): JsonResponse {
        $organization = $this->organizationFromToken($request);

        abort_unless(
            $conversation->organization_id === $organization->id,
            404
        );

        $validated = $request->validate([
            'message' => [
                'required',
                'string',
                'max:10000',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Après transfert humain
        |--------------------------------------------------------------------------
        |
        | ai_enabled = false
        |
        | Le message client doit toujours être enregistré.
        |
        */

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => null,
            'sender_type' => 'client',
            'content' => $validated['message'],
            'channel' => 'web',
            'ai_generated' => false,
            'ai_processed' => false,
            'ai_status' => $conversation->ai_enabled
                ? 'pending'
                : 'completed',
            'ai_error' => null,
        ]);

        $conversation->update([
            'last_message_at' => now(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Lancer l'IA uniquement si elle est active
        |--------------------------------------------------------------------------
        */

        if ($conversation->ai_enabled) {
            ProcessIncomingMessage::dispatch(
                $message->id
            );
        }

        return response()->json([
            'success' => true,
            'message_id' => $message->id,
            'message' => 'Message envoyé.',
            'ai_enabled' => (bool) $conversation->ai_enabled,
        ], 201);
    }

    public function messages(
        Request $request,
        Conversation $conversation
    ): JsonResponse {
        $organization = $this->organizationFromToken($request);

        abort_unless(
            $conversation->organization_id === $organization->id,
            404
        );

        /*
        |--------------------------------------------------------------------------
        | Récupération incrémentale
        |--------------------------------------------------------------------------
        */

        $afterId = $request->integer(
            'after_id',
            0
        );

        $query = $conversation
            ->messages()
            ->orderBy('created_at')
            ->orderBy('id');

        if ($afterId > 0) {
            $query->where(
                'id',
                '>',
                $afterId
            );
        }

        $messages = $query->get([
            'id',
            'sender_type',
            'content',
            'ai_generated',
            'ai_status',
            'created_at',
            'user_id',
        ]);

        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->id,
            'ai_enabled' => (bool) $conversation->ai_enabled,
            'status' => $conversation->status,
            'messages' => $messages,
        ]);
    }

    private function organizationFromToken(
        Request $request
    ): Organization {
        $token = $request->header(
            'X-Widget-Token'
        );

        abort_unless(
            $token,
            401
        );

        $organization = Organization::where(
            'widget_token',
            $token
        )->first();

        abort_unless(
            $organization,
            401
        );

        return $organization;
    }
}
