<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\RestrictsToAgent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Call extends Model
{
    use BelongsToOrganization, RestrictsToAgent;

    use HasFactory;

    /**
     * Cycle de vie complet d'un appel.
     *
     * ringing   : le poste de l'agent sonne
     * answered  : l'agent a décroché
     * completed : conversation terminée normalement
     * missed    : personne n'a décroché
     * busy      : tous les agents étaient occupés
     * cancelled : l'appelant a raccroché avant réponse
     * failed    : erreur technique
     */
    public const STATUSES = [
        'ringing',
        'answered',
        'completed',
        'missed',
        'busy',
        'failed',
        'cancelled',
    ];

    /**
     * États qu'un agent peut poser à la main depuis la console.
     *
     * « ringing » en est exclu : un appel qui sonne est un état
     * transitoire, produit par le système, que personne ne saisit.
     */
    public const MANUAL_STATUSES = [
        'answered',
        'completed',
        'missed',
        'busy',
        'failed',
        'cancelled',
    ];

    protected $fillable = [
        'organization_id',
        'client_id',
        'conversation_id',
        'user_id',
        'type',
        'status',
        'ai_handled',
        'provider',
        'provider_call_id',
        'transcript',
        'phone',
        'duration',
        'reason',
        'notes',
        'transcript',
        'answered_at',
        'declined_by',
        'started_at',
        'ended_at',
    ];

    /**
     * Colonne d'attribution propre aux appels.
     *
     * Les conversations et les tickets emploient « assigned_to » ;
     * ici, c'est l'agent qui a pris l'appel.
     */
    public function agentColumn(): string
    {
        return 'user_id';
    }

    protected $casts = [
        'duration' => 'integer',
        'ai_handled' => 'boolean',
        'transcript' => 'array',
        'started_at' => 'datetime',
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
    ];


    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}