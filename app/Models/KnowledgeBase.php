<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBase extends Model
{
    use BelongsToOrganization;

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

