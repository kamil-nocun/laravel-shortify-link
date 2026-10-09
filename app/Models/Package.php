<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property int $link_limit
 * @property int|null $price_one_time
 * @property int|null $price_monthly
 * @property int|null $price_quarterly
 * @property int|null $price_yearly
 * @property bool $is_default
 * @property string|null $stripe_product_id
 * @property string|null $stripe_price_id_one_time
 * @property string|null $stripe_price_id_monthly
 * @property string|null $stripe_price_id_quarterly
 * @property string|null $stripe_price_id_yearly
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'link_limit', 'price_one_time', 'price_monthly', 'price_quarterly', 'price_yearly', 'is_default'])]
class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /**
     * @return HasMany<PackageAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(PackageAssignment::class);
    }

    public function stripePriceId(BillingPeriod $period): ?string
    {
        return $this->{'stripe_price_id_'.$period->value};
    }

    public function priceFor(BillingPeriod $period): ?int
    {
        return $this->{$period->priceColumn()};
    }

    /**
     * Periods that have a price in this package.
     *
     * @return list<BillingPeriod>
     */
    public function availablePeriods(): array
    {
        return array_values(array_filter(BillingPeriod::cases(), fn (BillingPeriod $period) => $this->priceFor($period) !== null));
    }
}
