<?php

namespace Tests\Support;

use App\Billing\PackageBilling;
use App\Enums\BillingPeriod;
use App\Models\Package;
use App\Models\User;
use RuntimeException;

class FakePackageBilling implements PackageBilling
{
    /**
     * @var list<array{0: string, 1: array<int, mixed>}>
     */
    public array $calls = [];

    public bool $failSync = false;

    public ?string $pendingInvoiceUrl = null;

    public function syncPackage(Package $package): void
    {
        $this->calls[] = ['syncPackage', [$package->id]];

        if ($this->failSync) {
            throw new RuntimeException('Stripe is unavailable.');
        }
    }

    public function retirePackage(Package $package): void
    {
        $this->calls[] = ['retirePackage', [$package->id]];
    }

    public function checkout(User $user, Package $package, BillingPeriod $period, string $successUrl, string $cancelUrl): string
    {
        $this->calls[] = ['checkout', [$user->id, $package->id, $period->value]];

        return 'https://checkout.stripe.test/session';
    }

    public function invoice(User $user, Package $package, BillingPeriod $period): void
    {
        $this->calls[] = ['invoice', [$user->id, $package->id, $period->value]];
    }

    public function pendingInvoiceUrl(User $user): ?string
    {
        return $this->pendingInvoiceUrl;
    }

    /**
     * Names of the calls made so far.
     *
     * @return list<string>
     */
    public function called(): array
    {
        return array_column($this->calls, 0);
    }
}
