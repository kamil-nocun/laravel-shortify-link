<?php

namespace App\Models;

use App\Enums\LinkStatus;
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
 * @property string|null $alias
 * @property-read string $short_url
 * @property-read string|null $alias_url
 * @property bool $is_active
 * @property Carbon|null $expires_at
 * @property Carbon|null $disabled_by_admin_at
 * @property string|null $disabled_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['domain_id', 'title', 'original_url', 'code', 'alias', 'is_active', 'expires_at'])]
class Link extends Model
{
    /** @use HasFactory<LinkFactory> */
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $appends = ['short_url', 'alias_url'];

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
     * Full public address of the short link; derived, never stored; the scheme and port come from APP_URL.
     *
     * @return Attribute<string, never>
     */
    protected function shortUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->urlFor($this->code));
    }

    /**
     * Public address of the custom alias, or null when the link has none.
     *
     * @return Attribute<string|null, never>
     */
    protected function aliasUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->alias === null ? null : $this->urlFor($this->alias));
    }

    private function urlFor(string $path): string
    {
        $base = $this->domain_id !== null
            ? Str::of($this->domain->url())
            : Str::of(config('app.url'))->rtrim('/');

        return $base->append('/', $path)->toString();
    }

    /**
     * Host (with port, if any) of the main application domain.
     */
    public static function mainHost(): string
    {
        return parse_url(config('app.url'), PHP_URL_HOST).(parse_url(config('app.url'), PHP_URL_PORT) ? ':'.parse_url(config('app.url'), PHP_URL_PORT) : '');
    }

    /**
     * Whether the value is already a code or alias of another link within the domain (null = main domain).
     */
    public static function isCodeOrAliasTaken(?string $domainId, string $value, ?string $ignoreLinkId = null): bool
    {
        return static::where('domain_id', $domainId)
            ->where(fn (Builder $query) => $query->where('code', $value)->orWhere('alias', $value))
            ->when($ignoreLinkId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreLinkId))
            ->exists();
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
     * Links whose title, code or target URL contains the term; LIKE wildcards in the term are literal.
     *
     * @param  Builder<Link>  $query
     */
    #[Scope]
    protected function matching(Builder $query, string $term): void
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(fn (Builder $query) => $query->where('title', 'like', $like)
            ->orWhere('code', 'like', $like)
            ->orWhere('alias', 'like', $like)
            ->orWhere('original_url', 'like', $like));
    }

    /**
     * Links in the given status; precedence is disabled, expired, inactive, active.
     *
     * @param  Builder<Link>  $query
     */
    #[Scope]
    protected function withStatus(Builder $query, LinkStatus $status): void
    {
        match ($status) {
            LinkStatus::Disabled => $query->whereNotNull('disabled_by_admin_at'),
            LinkStatus::Expired => $query->whereNull('disabled_by_admin_at')->where('expires_at', '<=', now()),
            LinkStatus::Inactive => $query->whereNull('disabled_by_admin_at')
                ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->where('is_active', false),
            LinkStatus::Active => $query->active(),
        };
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
