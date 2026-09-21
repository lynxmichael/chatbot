<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    /**
     * Tient « active_for » aligné sur le statut.
     *
     * Cette colonne porte la contrainte d'unicité en base : elle vaut
     * l'organisation tant que la souscription est active, NULL ensuite.
     * Plusieurs NULL cohabitent dans un index unique, une seule valeur
     * non nulle est admise — c'est ce qui garantit, au niveau du
     * moteur, qu'une entreprise n'a jamais deux abonnements actifs.
     *
     * La maintenir ici plutôt qu'à chaque écriture évite d'avoir à y
     * penser, et donc de l'oublier.
     */
    protected static function booted(): void
    {
        static::saving(function (self $subscription) {
            $subscription->active_for = $subscription->status === 'active'
                ? $subscription->organization_id
                : null;
        });
    }

    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'plan',
        'status',
        'active_for',
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
