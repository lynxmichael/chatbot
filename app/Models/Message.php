<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
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
