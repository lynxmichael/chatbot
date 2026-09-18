<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeImage extends Model
{
    protected $fillable = [
        'organization_id',
        'knowledge_base_id',
        'path',
        'caption',
        'position',
    ];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class, 'knowledge_base_id');
    }

    public function url(): string
    {
        return asset('storage/' . $this->path);
    }
}
