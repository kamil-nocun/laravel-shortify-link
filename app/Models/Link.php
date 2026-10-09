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
 * @property string|null $title
 * @property string $original_url
 * @property string $code
 * @property-read string $short_url
 * @property bool $is_active
 * @property Carbon|null $expires_at
 * @property Carbon|null $disabled_by_admin_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'original_url', 'code', 'is_active', 'expires_at'])]
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
        return Attribute::get(fn (): string => Str::of(config('app.url'))->rtrim('/')->append('/', $this->code)->toString());
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
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isDisabledByAdmin(): bool
    {
        return $this->disabled_by_admin_at !== null;
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
