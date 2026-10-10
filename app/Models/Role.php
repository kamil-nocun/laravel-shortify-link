<?php

namespace App\Models;

use App\Enums\Permission;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_super
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['is_super' => 'boolean', 'is_default' => 'boolean'];
    }

    /**
     * @return BelongsToMany<PermissionRecord, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(PermissionRecord::class, 'permission_role', 'role_id', 'permission_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return Collection<int, string>
     */
    public function permissionKeys(): Collection
    {
        if ($this->is_super) {
            return collect(Permission::cases())->map->value;
        }

        return $this->permissions()->pluck('key');
    }

    /**
     * Ids of the non-super roles that grant admin panel access.
     *
     * @return list<string>
     */
    public static function administratorIds(): array
    {
        return self::where('is_super', false)
            ->whereHas('permissions', fn ($query) => $query->where('key', Permission::AdminAccess))
            ->pluck('id')->all();
    }
}
