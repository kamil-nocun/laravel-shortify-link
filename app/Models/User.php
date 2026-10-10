<?php

namespace App\Models;

use App\Enums\Permission;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Cashier\Billable;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $blocked_at
 * @property string|null $blocked_by
 * @property string|null $role_id
 * @property-read Role|null $role
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasFactory, HasUuids, Notifiable;

    /**
     * Give a user created without a role the default one.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->role_id ??= Role::where('is_default', true)->value('id');
        });
    }

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
            'blocked_at' => 'datetime',
        ];
    }

    /**
     * @var Collection<int, string>|null
     */
    private ?Collection $cachedPermissionKeys = null;

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsToMany<PermissionRecord, $this>
     */
    public function permissionOverrides(): BelongsToMany
    {
        return $this->belongsToMany(PermissionRecord::class, 'permission_user', 'user_id', 'permission_id')->withPivot('granted');
    }

    /**
     * Effective permission keys: the role's, plus granted overrides, minus revoked ones; every key for a super role.
     *
     * @return Collection<int, string>
     */
    public function permissionKeys(): Collection
    {
        return $this->cachedPermissionKeys ??= $this->resolvePermissionKeys();
    }

    public function hasPermission(Permission|string $key): bool
    {
        return $this->permissionKeys()->contains($key instanceof Permission ? $key->value : $key);
    }

    public function forgetPermissions(): void
    {
        $this->cachedPermissionKeys = null;
    }

    /**
     * @return Collection<int, string>
     */
    private function resolvePermissionKeys(): Collection
    {
        $role = $this->relationLoaded('role') ? $this->role : $this->role()->first();

        if ($role === null) {
            return collect();
        }

        if ($role->is_super) {
            return $role->permissionKeys();
        }

        $overrides = $this->relationLoaded('permissionOverrides') ? $this->permissionOverrides : $this->permissionOverrides()->get();

        return $role->permissionKeys()
            ->merge($overrides->filter(fn (PermissionRecord $permission) => $permission->pivot->granted)->pluck('key'))
            ->diff($overrides->reject(fn (PermissionRecord $permission) => $permission->pivot->granted)->pluck('key'))
            ->unique()->values();
    }

    /**
     * The super admin and any role with admin panel access; admin.access is role-only, so overrides are ignored.
     */
    public function isAdmin(): bool
    {
        $role = $this->role;

        return $role !== null && ($role->is_super || $role->loadMissing('permissions')->permissionKeys()->contains(Permission::AdminAccess->value));
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->is_super ?? false;
    }

    public function isClient(): bool
    {
        return ! $this->isAdmin();
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'blocked_by');
    }

    /**
     * @return HasMany<Domain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    /**
     * @return HasMany<Link, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasOne<PackageAssignment, $this>
     */
    public function packageAssignment(): HasOne
    {
        return $this->hasOne(PackageAssignment::class)
            ->ofMany(['starts_at' => 'max'], fn (Builder $query) => $query->where('starts_at', '<=', now()));
    }

    /**
     * The package bought to start once the current one ends, if any.
     */
    public function queuedAssignment(): ?PackageAssignment
    {
        return PackageAssignment::with('package')->where('user_id', $this->id)->where('starts_at', '>', now())->orderBy('starts_at')->first();
    }

    /**
     * The assignment currently in force, if any.
     */
    public function activeAssignment(): ?PackageAssignment
    {
        $assignment = $this->packageAssignment()->with('package')->first();

        return $assignment?->isActive() ? $assignment : null;
    }

    /**
     * Effective package: the active assignment, else the default package; none for a super admin.
     */
    public function package(): ?Package
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        return $this->activeAssignment()?->package ?? Package::where('is_default', true)->first();
    }

    /**
     * Maximum number of links, or null when unlimited. An assignment's snapshot wins over later package edits.
     */
    public function linkLimit(): ?int
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        return $this->activeAssignment()?->link_limit ?? Package::where('is_default', true)->value('link_limit') ?? 0;
    }

    /**
     * Maximum number of custom domains, or null when unlimited. An assignment's snapshot wins over later package edits.
     */
    public function domainLimit(): ?int
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        return $this->activeAssignment()?->domain_limit ?? Package::where('is_default', true)->value('domain_limit') ?? 0;
    }

    public function canAddDomain(): bool
    {
        $limit = $this->domainLimit();

        return $limit === null || $this->domains()->count() < $limit;
    }

    public function canCreateLink(): bool
    {
        $limit = $this->linkLimit();

        return $limit === null || $this->links()->count() < $limit;
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

        $query->clients()
            ->whereHas('assignedAdmins', fn (Builder $query) => $query->whereKey($actor->id));
    }

    /**
     * Users whose role is neither super nor grants admin panel access; users without a role count as clients.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function clients(Builder $query): void
    {
        $query->whereDoesntHave('role', fn (Builder $role) => $role->where('is_super', true)
            ->orWhereHas('permissions', fn (Builder $permission) => $permission->where('key', Permission::AdminAccess)));
    }

    /**
     * Users whose non-super role grants admin panel access.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function administrators(Builder $query): void
    {
        $query->whereHas('role', fn (Builder $role) => $role->where('is_super', false)
            ->whereHas('permissions', fn (Builder $permission) => $permission->where('key', Permission::AdminAccess)));
    }
}
