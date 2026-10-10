<?php

namespace App\Billing;

use App\Enums\BillingPeriod;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;

interface PackageBilling
{
    /**
     * Cancel the user's subscription at the end of the paid period.
     */
    public function cancelSubscription(Subscription $subscription): void;

    /**
     * Create or update the Stripe product and prices of the package and store their identifiers.
     */
    public function syncPackage(Package $package): void;

    /**
     * Archive the package in Stripe and schedule cancellation, at period end, of its running subscriptions.
     */
    public function retirePackage(Package $package): void;

    /**
     * Stripe Checkout address for the user to buy the package for the period.
     */
    public function checkout(User $user, Package $package, BillingPeriod $period, string $successUrl, string $cancelUrl): string;

    /**
     * Create a subscription (or a one-off invoice) for the user and e-mail them the invoice to pay.
     */
    public function invoice(User $user, Package $package, BillingPeriod $period): void;

    /**
     * Stripe-hosted page where the user pays their open invoice, or null when none is waiting.
     */
    public function pendingInvoiceUrl(User $user): ?string;

    /**
     * Every Stripe invoice of the user, as arrays shaped like the Stripe invoice object.
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function invoicesFor(User $user): iterable;
}
