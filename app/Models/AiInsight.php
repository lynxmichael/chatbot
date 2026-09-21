<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiInsight extends Model
{
    use BelongsToOrganization;

    use HasFactory;

    protected $fillable = [
        'organization_id',
        'fingerprint',
        'type',
        'severity',
        'title',
        'detail',
        'subject_type',
        'subject_id',
        'user_id',
        'client_id',
        'metrics',
        'status',
        'detected_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }


    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'acknowledged']);
    }

    public function scopeCritical($query)
    {
        return $query->whereIn('severity', ['high', 'critical']);
    }
}
