<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAction extends Model
{
    use BelongsToOrganization;

    use HasFactory;

    protected $fillable = [
        'organization_id',
        'conversation_id',
        'client_id',
        'ticket_id',
        'message_id',
        'tool',
        'input',
        'output',
        'status',
        'autopilot_level',
        'reason',
        'reviewed_by',
        'reviewed_at',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'reviewed_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }


    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Actions en attente de validation humaine.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
