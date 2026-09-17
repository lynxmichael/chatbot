<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsagePeriod extends Model
{
    protected $fillable = [
        'organization_id',
        'period',
        'ai_messages',
        'input_tokens',
        'output_tokens',
        'voice_calls',
        'voice_seconds',
        'conversations',
        'warned_at',
        'blocked_at',
    ];

    protected function casts(): array
    {
        return [
            'ai_messages' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'voice_calls' => 'integer',
            'voice_seconds' => 'integer',
            'conversations' => 'integer',
            'warned_at' => 'datetime',
            'blocked_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
