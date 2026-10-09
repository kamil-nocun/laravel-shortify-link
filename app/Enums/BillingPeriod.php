<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum BillingPeriod: string
{
    case OneTime = 'one_time';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    /**
     * Name of the package column that holds the price for this period.
     */
    public function priceColumn(): string
    {
        return 'price_'.$this->value;
    }

    /**
     * Expiry of a period starting at the given moment; a one-time period lasts one calendar month.
     */
    public function expiresAt(CarbonInterface $startsAt): CarbonInterface
    {
        return match ($this) {
            self::OneTime, self::Monthly => $startsAt->copy()->addMonthsNoOverflow(1),
            self::Quarterly => $startsAt->copy()->addMonthsNoOverflow(3),
            self::Yearly => $startsAt->copy()->addYearsNoOverflow(1),
        };
    }
}
