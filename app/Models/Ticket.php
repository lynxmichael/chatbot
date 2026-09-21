<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\RestrictsToAgent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use BelongsToOrganization, RestrictsToAgent;

    use HasFactory;

    protected $fillable = [
        'organization_id',
        'client_id',
        'conversation_id',
        'assigned_to',
        'ticket_number',
        'subject',
        'description',
        'category',
        'status',
        'priority',
        'channel',
        'sla_due_at',
        'first_response_at',
        'resolution',
        'resolved_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'sla_due_at' => 'datetime',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class
        );
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(
            Client::class
        );
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(
            Conversation::class
        );
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assigned_to'
        );
    }
}
