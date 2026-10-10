<?php

namespace App\Billing;

use App\Enums\BillingPeriod;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use App\Support\StripeSandboxGuard;
use Laravel\Cashier\Cashier;

class StripePackageBilling implements PackageBilling
{
    private const INVOICE_DAYS_UNTIL_DUE = 7;

    public function syncPackage(Package $package): void
    {
        StripeSandboxGuard::assertTestMode();

        $stripe = Cashier::stripe();

        if ($package->stripe_product_id === null) {
            $package->forceFill([
                'stripe_product_id' => $stripe->products->create(['name' => $package->name, 'metadata' => ['package_id' => $package->id]])->id,
            ]);
        } else {
            $stripe->products->update($package->stripe_product_id, ['name' => $package->name, 'active' => true]);
        }

        foreach (BillingPeriod::cases() as $period) {
            $column = 'stripe_price_id_'.$period->value;
            $amount = $package->priceFor($period);
            $existing = $package->{$column};

            if ($existing !== null && $amount !== null && $stripe->prices->retrieve($existing)->unit_amount === $amount) {
                continue;
            }

            if ($existing !== null) {
                $stripe->prices->update($existing, ['active' => false]);
            }

            $package->forceFill([$column => $amount === null ? null : $stripe->prices->create($this->priceParameters($package, $period, $amount))->id]);
        }

        $package->save();
    }

    public function retirePackage(Package $package): void
    {
        StripeSandboxGuard::assertTestMode();

        $stripe = Cashier::stripe();
        $priceIds = array_filter(array_map(fn (BillingPeriod $period) => $package->{'stripe_price_id_'.$period->value}, BillingPeriod::cases()));

        Subscription::whereIn('stripe_price', $priceIds)->whereNull('ends_at')->get()->each->cancel();

        foreach ($priceIds as $priceId) {
            $stripe->prices->update($priceId, ['active' => false]);
        }

        if ($package->stripe_product_id !== null) {
            $stripe->products->update($package->stripe_product_id, ['active' => false]);
        }
    }

    public function cancelSubscription(Subscription $subscription): void
    {
        StripeSandboxGuard::assertTestMode();

        $subscription->cancel();
    }

    public function checkout(User $user, Package $package, BillingPeriod $period, string $successUrl, string $cancelUrl): string
    {
        StripeSandboxGuard::assertTestMode();

        $priceId = $package->{'stripe_price_id_'.$period->value};
        $options = ['success_url' => $successUrl, 'cancel_url' => $cancelUrl];

        $checkout = $period === BillingPeriod::OneTime
            ? $user->checkout([$priceId => 1], [...$options, 'invoice_creation' => ['enabled' => true]])
            : $user->newSubscription('default', $priceId)->checkout($options);

        return $checkout->url;
    }

    public function invoice(User $user, Package $package, BillingPeriod $period): void
    {
        StripeSandboxGuard::assertTestMode();

        $priceId = $package->{'stripe_price_id_'.$period->value};

        if ($period !== BillingPeriod::OneTime) {
            $user->newSubscription('default', $priceId)->createAndSendInvoice([], ['days_until_due' => self::INVOICE_DAYS_UNTIL_DUE]);

            return;
        }

        $stripe = Cashier::stripe();
        $customer = $user->createOrGetStripeCustomer()->id;

        $stripe->invoiceItems->create(['customer' => $customer, 'pricing' => ['price' => $priceId]]);
        $invoice = $stripe->invoices->create(['customer' => $customer, 'collection_method' => 'send_invoice', 'pending_invoice_items_behavior' => 'include', 'days_until_due' => self::INVOICE_DAYS_UNTIL_DUE]);
        $stripe->invoices->sendInvoice($stripe->invoices->finalizeInvoice($invoice->id)->id);
    }

    public function pendingInvoiceUrl(User $user): ?string
    {
        StripeSandboxGuard::assertTestMode();

        if ($user->stripe_id === null) {
            return null;
        }

        return Cashier::stripe()->invoices->all(['customer' => $user->stripe_id, 'status' => 'open', 'limit' => 1])->data[0]->hosted_invoice_url ?? null;
    }

    public function invoicesFor(User $user): iterable
    {
        StripeSandboxGuard::assertTestMode();

        if ($user->stripe_id === null) {
            return;
        }

        foreach (Cashier::stripe()->invoices->all(['customer' => $user->stripe_id, 'limit' => 100])->autoPagingIterator() as $invoice) {
            yield $invoice->toArray();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function priceParameters(Package $package, BillingPeriod $period, int $amount): array
    {
        $parameters = [
            'product' => $package->stripe_product_id,
            'currency' => config('cashier.currency'),
            'unit_amount' => $amount,
            'metadata' => ['package_id' => $package->id, 'billing_period' => $period->value],
        ];

        $recurring = match ($period) {
            BillingPeriod::OneTime => null,
            BillingPeriod::Monthly => ['interval' => 'month', 'interval_count' => 1],
            BillingPeriod::Quarterly => ['interval' => 'month', 'interval_count' => 3],
            BillingPeriod::Yearly => ['interval' => 'year', 'interval_count' => 1],
        };

        return $recurring === null ? $parameters : [...$parameters, 'recurring' => $recurring];
    }
}
