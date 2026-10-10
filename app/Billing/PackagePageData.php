<?php

namespace App\Billing;

use App\Enums\BillingPeriod;
use App\Models\Package;
use App\Models\User;
use Throwable;

class PackagePageData
{
    public function __construct(private readonly PackageBilling $billing) {}

    /**
     * The package page as the given account sees it.
     *
     * @return array{current: array<string, mixed>, canBuy: bool, awaitingPayment: bool, invoiceUrl: ?string, packages: list<array<string, mixed>>}
     */
    public function for(User $user): array
    {
        $assignment = $user->activeAssignment();
        $invoiceUrl = $this->pendingInvoiceUrl($user);

        return [
            'current' => [
                'package_name' => $user->package()?->name,
                'link_limit' => $user->linkLimit(),
                'billing_period' => $assignment?->billing_period->value,
                'expires_at' => $assignment?->expires_at->toIso8601String(),
            ],
            'canBuy' => ! $user->isSuperAdmin(),
            'awaitingPayment' => $invoiceUrl !== null || $user->subscriptions()->incomplete()->exists(),
            'invoiceUrl' => $invoiceUrl,
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
     * Stripe payment page of the open invoice; null when none is open or Stripe fails.
     */
    public function pendingInvoiceUrl(User $user): ?string
    {
        try {
            return $this->billing->pendingInvoiceUrl($user);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
