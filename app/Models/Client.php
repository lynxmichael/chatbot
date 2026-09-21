<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Ticket;

class Client extends Model
{
    use BelongsToOrganization;

    use HasFactory;

    protected $fillable = [
        'organization_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'company',
        'address',
        'city',
        'country',
        'status',
        'notes',
    ];


    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
    public function conversations(): HasMany
{
    return $this->hasMany(Conversation::class);
}
public function calls(): HasMany
{
    return $this->hasMany(Call::class);
}
public function tickets()
{
    return $this->hasMany(
        Ticket::class
    );
}
}
