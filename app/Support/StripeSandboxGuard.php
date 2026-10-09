<?php

namespace App\Support;

use RuntimeException;

class StripeSandboxGuard
{
    /**
     * Refuse Stripe operations with a live key outside production; this app only trades in Stripe test mode.
     */
    public static function assertTestMode(): void
    {
        if (app()->isProduction()) {
            return;
        }

        if (! str_starts_with((string) config('cashier.secret'), 'sk_test_')) {
            throw new RuntimeException('Stripe operations outside production require a test secret key (sk_test_…).');
        }
    }
}
