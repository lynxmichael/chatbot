<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Ticket;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'client_id',
        'assigned_to',
        'subject',
        'channel',
        'status',
        'priority',
        'ai_enabled',
        'ai_intent',
        'ai_sentiment',
        'ai_summary',
        'ai_confidence',
        'ai_last_run_at',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'ai_enabled' => 'boolean',
            'ai_confidence' => 'float',
            'ai_last_run_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Agent ou responsable affecté à la conversation.
     */
    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }
    public function ticket()
{
    return $this->hasOne(
        Ticket::class
    );
}

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function aiActions(): HasMany
    {
        return $this->hasMany(AiAction::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }
}
