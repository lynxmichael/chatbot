<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'status',
        'widget_token',
        'ai_settings',
    ];

    protected function casts(): array
    {
        return [
            'ai_settings' => 'array',
        ];
    }

    /**
     * Réglages IA de l'organisation, complétés par les valeurs
     * par défaut définies dans config/ai.php.
     */
    public function aiSettings(): array
    {
        return array_merge(
            config('ai.autopilot'),
            is_array($this->ai_settings) ? $this->ai_settings : []
        );
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
    public function clients(): HasMany
{
    return $this->hasMany(Client::class);
}
public function conversations(): HasMany
{
    return $this->hasMany(Conversation::class);
}
public function calls(): HasMany
{
    return $this->hasMany(Call::class);
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
