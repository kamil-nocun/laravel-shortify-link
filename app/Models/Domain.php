<?php

namespace App\Models;

use Database\Factories\DomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $host
 * @property string $verification_token
 * @property Carbon|null $verified_at
 * @property Carbon|null $disabled_by_admin_at
 * @property string|null $disabled_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['host', 'verification_token'])]
class Domain extends Model
{
    /** @use HasFactory<DomainFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'disabled_by_admin_at' => 'datetime',
        ];
    }

    /**
     * Verified domains not blocked by an admin whose owner is not blocked.
     *
     * @param  Builder<Domain>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNotNull('verified_at')
            ->whereNull('disabled_by_admin_at')
            ->whereHas('user', fn (Builder $query) => $query->whereNull('blocked_at'));
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function markVerified(): void
    {
        $this->forceFill(['verified_at' => now()])->save();
    }

    public function isActive(): bool
    {
        return $this->isVerified()
            && $this->disabled_by_admin_at === null
            && $this->user->blocked_at === null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function disabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disabled_by');
    }

    /**
     * @return HasMany<Link, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }
}
