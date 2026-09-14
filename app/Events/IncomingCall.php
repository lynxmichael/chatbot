<?php

namespace App\Events;

use App\Models\Call;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncomingCall implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    /**
     * L'appel entrant.
     */
    public Call $call;

    /**
     * Créer un nouvel événement.
     */
    public function __construct(Call $call)
    {
        $this->call = $call->load([
            'client',
            'conversation',
            'user',
        ]);
    }

    /**
     * Canal privé de l'agent concerné.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agent.' . $this->call->user_id),
        ];
    }

    /**
     * Nom de l'événement envoyé au navigateur.
     */
    public function broadcastAs(): string
    {
        return 'incoming.call';
    }

    /**
     * Données envoyées à l'interface de l'agent.
     */
    public function broadcastWith(): array
    {
        return [
            'call' => [
                'id' => $this->call->id,
                'status' => $this->call->status,
                'type' => $this->call->type,
                'phone' => $this->call->phone,
                'started_at' => $this->call->started_at,
                'ended_at' => $this->call->ended_at,
            ],

            'client' => $this->call->client ? [
                'id' => $this->call->client->id,
                'name' => $this->call->client->full_name,
                'email' => $this->call->client->email,
                'phone' => $this->call->client->phone,
            ] : null,

            'conversation' => $this->call->conversation ? [
                'id' => $this->call->conversation->id,
                'subject' => $this->call->conversation->subject,
            ] : null,

            'agent' => $this->call->user ? [
                'id' => $this->call->user->id,
                'name' => $this->call->user->name,
            ] : null,
        ];
    }
}
