<?php

namespace App\Models;

use Database\Factories\LinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $user_id
 * @property string|null $domain_id
 * @property string|null $title
 * @property string $original_url
 * @property string $code
 * @property-read string $short_url
 * @property bool $is_active
 * @property Carbon|null $expires_at
 * @property Carbon|null $disabled_by_admin_at
 * @property string|null $disabled_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['domain_id', 'title', 'original_url', 'code', 'is_active', 'expires_at'])]
class Link extends Model
{
    /** @use HasFactory<LinkFactory> */
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $appends = ['short_url'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'disabled_by_admin_at' => 'datetime',
        ];
    }

    /**
     * Full public address of the short link; derived, never stored; the scheme comes from APP_URL.
     *
     * @return Attribute<string, never>
     */
    protected function shortUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $base = Str::of(config('app.url'))->rtrim('/');

            if ($this->domain_id !== null) {
                $base = Str::of(parse_url((string) $base, PHP_URL_SCHEME).'://'.$this->domain->host);
            }

            return $base->append('/', $this->code)->toString();
        });
    }

    /**
     * Links that are switched on and not yet expired.
     *
     * @param  Builder<Link>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereNull('disabled_by_admin_at')
            ->whereHas('user', fn (Builder $query) => $query->whereNull('blocked_at'))
            ->where(fn (Builder $query) => $query->whereNull('domain_id')->orWhereHas('domain', fn (Builder $query) => $query->active()))
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * Links the given actor may manage, derived from the owners visible to them.
     *
     * @param  Builder<Link>  $query
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

        $query->whereHas('user', fn (Builder $query) => $query->visibleTo($actor));
    }

    public function isDisabledByAdmin(): bool
    {
        return $this->disabled_by_admin_at !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function disabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disabled_by');
    }

    /**
     * @return BelongsTo<Domain, $this>
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<LinkClick, $this>
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(LinkClick::class);
    }
}
