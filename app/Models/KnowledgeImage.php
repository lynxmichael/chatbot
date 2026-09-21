<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeImage extends Model
{
    use BelongsToOrganization;

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


    public function entry(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class, 'knowledge_base_id');
    }

    public function url(): string
    {
        return asset('storage/' . $this->path);
    }
}
