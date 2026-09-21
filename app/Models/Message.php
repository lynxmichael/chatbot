<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    /**
     * Cloisonnement indirect.
     *
     * Un message ne porte pas d'organisation : il appartient à une
     * conversation, qui elle en porte une. Sans ce scope, une requête
     * distraite du type « Message::find($id) » lirait le message de
     * n'importe quelle entreprise.
     *
     * Le filtre s'appuie sur la conversation, dont le propre
     * cloisonnement s'applique alors en cascade : une seule règle à
     * maintenir plutôt que deux.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('organization', function ($builder) {
            $user = \Illuminate\Support\Facades\Auth::user();

            if (!$user || !$user->organization_id || $user->isSuperAdmin()) {
                return;
            }

            $builder->whereHas('conversation');
        });
    }

    use HasFactory;

protected $fillable = [
    'conversation_id',
    'user_id',
    'sender_type',
    'content',
    'channel',
    'ai_generated',
    'ai_processed',
    'ai_status',
    'ai_error',
    'metadata',
    'read_at',
];
    protected function casts(): array
    {
        return [
            'ai_generated' => 'boolean',
            'ai_processed' => 'boolean',
            'metadata' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
