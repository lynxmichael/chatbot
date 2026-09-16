<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUp extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'client_id',
        'conversation_id',
        'ticket_id',
        'instruction',
        'channel',
        'run_at',
        'status',
        'attempts',
        'last_error',
        'created_by_ai',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'run_at' => 'datetime',
            'executed_at' => 'datetime',
            'created_by_ai' => 'boolean',
            'attempts' => 'integer',
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

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Relances arrivées à échéance et non encore exécutées.
     */
    public function scopeDue($query)
    {
        return $query->where('status', 'pending')
            ->where('run_at', '<=', now());
    }
}
