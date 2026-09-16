<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessIncomingMessage;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function test(
        Request $request,
        Conversation $conversation
    ) {
        $user = $request->user();

        abort_unless(
            $conversation->organization_id === $user->organization_id,
            403
        );

        if ($user->role === 'agent') {
            abort_unless(
                $conversation->assigned_to === $user->id,
                403
            );
        } elseif ($user->role !== 'owner') {
            abort(403);
        }

        $validated = $request->validate([
            'message' => [
                'required',
                'string',
                'max:10000',
            ],
        ]);

        abort_unless(
            $conversation->ai_enabled,
            422
        );

        /*
         * Création du message client à traiter par l'IA.
         */
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => null,
            'sender_type' => 'client',
            'content' => $validated['message'],
            'channel' => $conversation->channel,
            'ai_generated' => false,
            'ai_processed' => false,
            'ai_status' => 'pending',
            'ai_error' => null,
        ]);

        $conversation->update([
            'last_message_at' => now(),
        ]);

        /*
         * Envoi du message dans la file de traitement.
         */
        ProcessIncomingMessage::dispatch(
            $message->id
        );

        return back()->with(
            'success',
            'Message envoyé au traitement IA.'
        );
    }
}
