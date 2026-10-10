<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $stripe_invoice_id
 * @property string|null $package_id
 * @property BillingPeriod|null $billing_period
 * @property PaymentStatus $status
 * @property int $amount
 * @property string $currency
 * @property string|null $invoice_url
 * @property Carbon $issued_at
 * @property Carbon|null $paid_at
 * @property Carbon $stripe_event_at
 */
#[Fillable(['user_id', 'stripe_invoice_id', 'package_id', 'billing_period', 'status', 'amount', 'currency', 'invoice_url', 'issued_at', 'paid_at', 'stripe_event_at'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_period' => BillingPeriod::class,
            'status' => PaymentStatus::class,
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'stripe_event_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class)->withTrashed();
    }
}
