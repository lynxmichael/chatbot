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
        'logo_path',
        'brand_color',
        'support_email',
        'welcome_message',
    ];

    protected function casts(): array
    {
        return [
            'ai_settings' => 'array',
        ];
    }

    /**
     * Réglages IA applicables à cette organisation.
     *
     * Trois couches, de la plus générale à la plus précise :
     *
     *     valeurs par défaut  <  formule  <  réglages propres
     *
     * La dernière couche permet d'accorder une exception à un client
     * — un plafond relevé, un outil de plus — sans le sortir de sa
     * formule ni toucher aux autres.
     */
    public function aiSettings(): array
    {
        $own = is_array($this->ai_settings) ? $this->ai_settings : [];

        $plan = config(
            'ai.plans.' . ($own['plan'] ?? config('ai.quota.plan', 'free')),
            []
        );

        $defaults = array_merge(
            config('ai.autopilot', []),
            ['quota' => config('ai.quota', [])]
        );

        return $this->mergeSettings(
            $this->mergeSettings($defaults, $plan),
            $own
        );
    }

    /**
     * Fusion récursive, sauf pour les listes.
     *
     * Une liste comme « allowed_actions » doit être remplacée en bloc :
     * fusionnée poste par poste, une formule de trois outils laisserait
     * passer les six suivants de la couche précédente.
     */
    private function mergeSettings(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (
                is_array($value)
                && !array_is_list($value)
                && is_array($base[$key] ?? null)
            ) {
                $base[$key] = $this->mergeSettings($base[$key], $value);

                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * Identité visuelle, avec des valeurs de repli.
     *
     * Le widget s'affiche sur le site du client : sans logo ni couleur,
     * il doit rester présentable plutôt que cassé.
     */
    public function branding(): array
    {
        return [
            'name' => $this->aiSettings()['business_name'] ?? $this->name,
            'logo' => $this->logo_path
                ? asset('storage/' . $this->logo_path)
                : null,
            'color' => $this->brand_color ?: '#4f46e5',
            'welcome' => $this->welcome_message
                ?: 'Bonjour, comment pouvons-nous vous aider ?',
            'support_email' => $this->support_email,
        ];
    }

    /**
     * Nom de la formule en vigueur.
     */
    public function plan(): string
    {
        return $this->aiSettings()['plan']
            ?? config('ai.quota.plan', 'free');
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
