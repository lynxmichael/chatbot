<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'subscription_id',
        'initiated_by',
        'plan',
        'amount',
        'currency',
        'provider',
        'reference',
        'provider_reference',
        'status',
        'method',
        'paid_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'payload' => 'array',
        ];
    }


    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}
