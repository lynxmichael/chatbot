<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Call extends Model
{
    use HasFactory;

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

    protected $casts = [
        'duration' => 'integer',
        'ai_handled' => 'boolean',
        'transcript' => 'array',
        'started_at' => 'datetime',
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

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