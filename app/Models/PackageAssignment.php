<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use Database\Factories\PackageAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $package_id
 * @property string|null $subscription_id
 * @property BillingPeriod $billing_period
 * @property int $price
 * @property int $link_limit
 * @property Carbon $starts_at
 * @property Carbon $expires_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'package_id', 'subscription_id', 'billing_period', 'price', 'link_limit', 'starts_at', 'expires_at', 'cancelled_at'])]
class PackageAssignment extends Model
{
    /** @use HasFactory<PackageAssignmentFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_period' => BillingPeriod::class,
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether the package will not renew: cancelled by the user or its subscription is winding down.
     */
    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null || ($this->subscription?->onGracePeriod() ?? false);
    }

    public function isActive(): bool
    {
        return ! $this->starts_at->isFuture() && $this->expires_at->isFuture();
    }
}
