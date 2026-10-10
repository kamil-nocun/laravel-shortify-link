<?php

namespace App\Billing;

use App\Enums\BillingPeriod;
use App\Models\Package;
use App\Models\PackageAssignment;
use App\Models\StripeEvent;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * Projects Stripe webhooks onto package assignments, applying each Stripe event at most once.
 */
class ProjectStripeEvents
{
    public function onReceived(WebhookReceived $event): void
    {
        if ($event->payload['type'] === 'invoice.payment_succeeded') {
            $this->once($event->payload, fn () => $this->applyPaidInvoice($event->payload['data']['object']));
        }
    }

    public function onHandled(WebhookHandled $event): void
    {
        if ($event->payload['type'] === 'customer.subscription.deleted') {
            $this->once($event->payload, fn () => $this->endSubscription($event->payload['data']['object']));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function once(array $payload, callable $apply): void
    {
        try {
            DB::transaction(function () use ($payload, $apply) {
                StripeEvent::create(['stripe_event_id' => $payload['id'], 'type' => $payload['type'], 'processed_at' => now()]);

                $apply();
            });
        } catch (UniqueConstraintViolationException) {
            // already applied
        }
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    private function applyPaidInvoice(array $invoice): void
    {
        $user = User::where('stripe_id', $invoice['customer'] ?? null)->first();
        $line = $invoice['lines']['data'][0] ?? [];
        $priceId = data_get($line, 'price.id') ?? data_get($line, 'pricing.price_details.price');
        $resolved = $priceId === null ? null : Package::findByStripePrice($priceId);

        if ($user === null || $resolved === null) {
            Log::warning('Stripe invoice for an unknown customer or price.', ['customer' => $invoice['customer'] ?? null, 'price' => $priceId]);

            return;
        }

        [$package, $period] = $resolved;
        $start = CarbonImmutable::createFromTimestamp(data_get($line, 'period.start', time()));
        $stripeSubscription = $invoice['subscription'] ?? data_get($invoice, 'parent.subscription_details.subscription');

        PackageAssignment::updateOrCreate(['user_id' => $user->id], [
            'package_id' => $package->id,
            'subscription_id' => $stripeSubscription === null ? null : Subscription::where('stripe_id', $stripeSubscription)->value('id'),
            'billing_period' => $period,
            'price' => $invoice['amount_paid'],
            'link_limit' => $package->link_limit,
            'starts_at' => $start,
            'expires_at' => $period === BillingPeriod::OneTime
                ? $start->addMonthNoOverflow()
                : CarbonImmutable::createFromTimestamp($line['period']['end']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    private function endSubscription(array $subscription): void
    {
        $id = Subscription::where('stripe_id', $subscription['id'] ?? null)->value('id');

        if ($id !== null) {
            PackageAssignment::where('subscription_id', $id)->delete();
        }
    }
}
