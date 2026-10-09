<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property UserRole $role
 * @property Carbon|null $blocked_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['role' => 'user'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'blocked_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    /**
     * @return HasMany<Link, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }

    /**
     * Administrators assigned to this client.
     *
     * @return BelongsToMany<User, $this, ClientAdmin>
     */
    public function assignedAdmins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'client_admin', 'client_id', 'admin_id')
            ->using(ClientAdmin::class)->withTimestamps();
    }

    /**
     * Clients assigned to this administrator.
     *
     * @return BelongsToMany<User, $this, ClientAdmin>
     */
    public function managedClients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'client_admin', 'admin_id', 'client_id')
            ->using(ClientAdmin::class)->withTimestamps();
    }

    /**
     * Users the given actor may manage: everyone for the super admin, assigned clients for an administrator.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $actor): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        if (! $actor->isAdmin()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('role', UserRole::User)
            ->whereHas('assignedAdmins', fn (Builder $query) => $query->whereKey($actor->id));
    }
}
