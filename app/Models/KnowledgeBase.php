<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBase extends Model
{
    protected $fillable = [
        'organization_id',
        'title',
        'category',
        'content',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Photos illustrant cette fiche.
     */
    public function images()
    {
        return $this->hasMany(KnowledgeImage::class)
            ->orderBy('position')
            ->orderBy('id');
    }
}

