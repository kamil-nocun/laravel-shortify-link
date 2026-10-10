<?php

namespace App\Billing;

use App\Enums\BillingPeriod;
use App\Enums\PaymentStatus;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use Throwable;

class PackagePageData
{
    public function __construct(private readonly PackageBilling $billing) {}

    /**
     * The package page as the given account sees it.
     *
     * @return array{current: array<string, mixed>, canBuy: bool, awaitingPayment: bool, invoiceUrl: ?string, payments: list<array<string, mixed>>, packages: list<array<string, mixed>>}
     */
    public function for(User $user): array
    {
        $assignment = $user->activeAssignment();
        $invoiceUrl = $this->invoiceUrlFor($user);

        return [
            'current' => [
                'package_name' => $user->package()?->name,
                'link_limit' => $user->linkLimit(),
                'billing_period' => $assignment?->billing_period->value,
                'expires_at' => $assignment?->expires_at->toIso8601String(),
            ],
            'canBuy' => ! $user->isSuperAdmin(),
            'awaitingPayment' => $this->isAwaitingPayment($user),
            'invoiceUrl' => $invoiceUrl,
            'payments' => $this->paymentsFor($user),
            'packages' => Package::where('is_default', false)->orderBy('name')->get()
                ->map(fn (Package $package) => [
                    'id' => $package->id,
                    'name' => $package->name,
                    'link_limit' => $package->link_limit,
                    'prices' => collect($package->availablePeriods())
                        ->filter(fn (BillingPeriod $period) => $package->stripePriceId($period) !== null)
                        ->mapWithKeys(fn (BillingPeriod $period) => [$period->value => $package->priceFor($period)])
                        ->all(),
                ])
                ->filter(fn (array $package) => $package['prices'] !== [])
                ->values()
                ->all(),
        ];
    }

    /**
     * The latest payments of the user, newest first.
     *
     * @return list<array{id: string, issued_at: string, package_name: ?string, billing_period: ?string, amount: int, status: string, invoice_url: ?string}>
     */
    public function paymentsFor(User $user): array
    {
        return $user->payments()->with('package')->latest('issued_at')->limit(20)->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'issued_at' => $payment->issued_at->toIso8601String(),
                'package_name' => $payment->package?->name,
                'billing_period' => $payment->billing_period?->value,
                'amount' => $payment->amount,
                'status' => $payment->status->value,
                'invoice_url' => $payment->invoice_url,
            ])->all();
    }

    /**
     * Payment page of the open invoice: from the stored payment, else asked from Stripe while a payment is pending.
     */
    public function invoiceUrlFor(User $user): ?string
    {
        $open = $user->payments()->where('status', PaymentStatus::Open)->latest('issued_at')->first();

        if ($open?->invoice_url !== null) {
            return $open->invoice_url;
        }

        return $this->isAwaitingPayment($user) ? $this->pendingInvoiceUrl($user) : null;
    }

    private function isAwaitingPayment(User $user): bool
    {
        return $user->payments()->where('status', PaymentStatus::Open)->exists()
            || $user->subscriptions()->incomplete()->exists();
    }

    private function pendingInvoiceUrl(User $user): ?string
    {
        try {
            return $this->billing->pendingInvoiceUrl($user);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
