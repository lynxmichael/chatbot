<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Réglages de la plateforme.
 *
 * Lus à chaque affichage de la page d'abonnement d'un client, d'où la
 * mise en cache : ce sont des valeurs qui changent trois fois par an.
 */
class PlatformSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever(
            'platform_settings',
            fn () => static::query()->pluck('value', 'key')->all()
        );

        return $all[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget('platform_settings');
    }

    /**
     * Coordonnées de règlement affichées aux clients.
     */
    public static function paymentDetails(): array
    {
        return static::get('payment_details', [
            'wave' => null,
            'orange_money' => null,
            'mtn_money' => null,
            'moov_money' => null,
            'bank_name' => null,
            'bank_iban' => null,
            'bank_holder' => null,
            'instructions' => null,
        ]);
    }
}
