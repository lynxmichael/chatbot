<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Ticket;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'password',
        'role',
        'is_super_admin',
        'skills',
        'is_active',
        'is_available',
        'max_open_tickets',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
            'is_available' => 'boolean',
            'skills' => 'array',
            'max_open_tickets' => 'integer',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Droits accordés par le rôle de cet utilisateur.
     */
    public function abilities(): array
    {
        $abilities = config('roles.roles.' . $this->role . '.abilities');

        return is_array($abilities) ? $abilities : [];
    }

    /**
     * Cet utilisateur dispose-t-il de ce droit ?
     *
     * Un administrateur de plateforme passe partout : il exploite le
     * service et doit pouvoir intervenir chez n'importe quel client.
     * Le propriétaire d'une entreprise possède « * » : il ne doit
     * jamais pouvoir être enfermé dehors de chez lui.
     */
    public function hasAbility(string $ability): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $abilities = $this->abilities();

        return in_array('*', $abilities, true)
            || in_array($ability, $abilities, true);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /**
     * Libellé lisible du rôle.
     */
    public function roleLabel(): string
    {
        return config(
            'roles.roles.' . $this->role . '.label',
            ucfirst((string) $this->role)
        );
    }

    /**
     * Administrateur de la plateforme, au-dessus des organisations.
     *
     * À ne pas confondre avec le rôle « owner », qui désigne le
     * responsable d'une entreprise cliente.
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Conversations attribuées à cet agent.
     */
    public function assignedConversations(): HasMany
    {
        return $this->hasMany(
            Conversation::class,
            'assigned_to'
        );
    }
    public function calls(): HasMany
{
    return $this->hasMany(Call::class);
}
public function assignedTickets()
{
    return $this->hasMany(
        Ticket::class,
        'assigned_to'
    );
}
}
