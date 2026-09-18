<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'organization_id',
        'plan',
        'status',
        'amount',
        'currency',
        'starts_at',
        'ends_at',
        'expiry_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'expiry_notified_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Jours restants avant l'échéance. Négatif si dépassée.
     */
    public function daysRemaining(): ?int
    {
        if (!$this->ends_at) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays(
            $this->ends_at->startOfDay(),
            false
        );
    }

    public function isExpired(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }
}
