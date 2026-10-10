<?php

namespace App\Billing;

use App\Enums\PaymentStatus;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * Keeps one payment row per Stripe invoice, applying only events newer than the stored state.
 */
class RecordInvoicePayment
{
    private const EVENTS = [
        'invoice.finalized',
        'invoice.payment_succeeded',
        'invoice.payment_failed',
        'invoice.voided',
        'invoice.marked_uncollectible',
    ];

    public function onReceived(WebhookReceived $event): void
    {
        $type = $event->payload['type'];

        if (! in_array($type, self::EVENTS, true)) {
            return;
        }

        $this->fromInvoice(
            $event->payload['data']['object'],
            $event->payload['created'] ?? time(),
            $type === 'invoice.payment_failed' ? PaymentStatus::PaymentFailed : null,
        );
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    public function fromInvoice(array $invoice, int $eventAt, ?PaymentStatus $status = null): ?Payment
    {
        $stripeStatus = $invoice['status'] ?? null;

        if ($stripeStatus === 'draft' || ($invoice['total'] ?? 0) <= 0) {
            return null;
        }

        $user = User::where('stripe_id', $invoice['customer'] ?? null)->first();

        if ($user === null) {
            Log::warning('Stripe invoice for an unknown customer.', ['customer' => $invoice['customer'] ?? null]);

            return null;
        }

        $line = $invoice['lines']['data'][0] ?? [];
        $priceId = data_get($line, 'price.id') ?? data_get($line, 'pricing.price_details.price');
        [$package, $period] = ($priceId === null ? null : Package::findByStripePrice($priceId)) ?? [null, null];
        $paidAt = data_get($invoice, 'status_transitions.paid_at');

        return DB::transaction(function () use ($invoice, $eventAt, $status, $stripeStatus, $user, $package, $period, $paidAt) {
            $payment = Payment::where('stripe_invoice_id', $invoice['id'])->lockForUpdate()->first();

            if ($payment !== null && $payment->stripe_event_at->timestamp > $eventAt) {
                return $payment;
            }

            $attributes = [
                'user_id' => $user->id,
                'package_id' => $package?->id,
                'billing_period' => $period,
                'status' => $status ?? $this->statusOf($stripeStatus),
                'amount' => $invoice['total'],
                'currency' => $invoice['currency'],
                'invoice_url' => $invoice['hosted_invoice_url'] ?? null,
                'issued_at' => CarbonImmutable::createFromTimestamp($invoice['created']),
                'paid_at' => $paidAt === null ? null : CarbonImmutable::createFromTimestamp($paidAt),
                'stripe_event_at' => CarbonImmutable::createFromTimestamp($eventAt),
            ];

            return $payment === null
                ? Payment::create(['stripe_invoice_id' => $invoice['id'], ...$attributes])
                : tap($payment)->update($attributes);
        });
    }

    private function statusOf(?string $stripeStatus): PaymentStatus
    {
        return match ($stripeStatus) {
            'paid' => PaymentStatus::Paid,
            'void' => PaymentStatus::Void,
            'uncollectible' => PaymentStatus::Uncollectible,
            default => PaymentStatus::Open,
        };
    }
}
