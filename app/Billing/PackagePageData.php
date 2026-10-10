<?php

namespace App\Billing;

use App\Enums\BillingPeriod;
use App\Enums\PaymentStatus;
use App\Models\Package;
use App\Models\PackageAssignment;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Throwable;

class PackagePageData
{
    public function __construct(private readonly PackageBilling $billing) {}

    /**
     * The package page as the given account sees it.
     *
     * @return array{current: array<string, mixed>, awaitingPayment: bool, invoiceUrl: ?string, payments: LengthAwarePaginator<int, array<string, mixed>>, assignments: LengthAwarePaginator<int, array<string, mixed>>, packages: list<array<string, mixed>>}
     */
    public function for(User $user): array
    {
        $invoiceUrl = $this->invoiceUrlFor($user);

        return [
            'current' => $this->current($user),
            'awaitingPayment' => $this->isAwaitingPayment($user),
            'invoiceUrl' => $invoiceUrl,
            'payments' => $this->paymentsFor($user),
            'assignments' => $this->assignmentsFor($user),
            'packages' => Package::where('is_default', false)->orderBy('name')->get()
                ->map(fn (Package $package) => [
                    'id' => $package->id,
                    'name' => $package->name,
                    'link_limit' => $package->link_limit,
                    'domain_limit' => $package->domain_limit,
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
     * The user's current package and what they may still do with it; makes no Stripe calls.
     *
     * @return array{package_name: ?string, link_limit: ?int, domain_limit: ?int, domains_used: int, billing_period: ?string, expires_at: ?string, cancellable: bool, can_buy: bool, next_package_name: ?string, next_starts_at: ?string, one_off: bool, cancels: bool}
     */
    public function current(User $user): array
    {
        $assignment = $user->activeAssignment();
        $subscription = $assignment?->subscription;
        $queued = $user->queuedAssignment();

        return [
            'package_name' => $user->package()?->name,
            'link_limit' => $user->linkLimit(),
            'domain_limit' => $user->domainLimit(),
            'domains_used' => $user->domains()->count(),
            'billing_period' => $assignment?->billing_period->value,
            'expires_at' => $assignment?->expires_at->toIso8601String(),
            'cancellable' => $assignment !== null && $assignment->cancelled_at === null && ! $subscription?->ended() && ! $subscription?->onGracePeriod(),
            'can_buy' => ($assignment === null || $assignment->isCancelled()) && $queued === null,
            'next_package_name' => $queued?->package->name,
            'next_starts_at' => $queued?->starts_at->toIso8601String(),
            'one_off' => $assignment !== null && $subscription === null,
            'cancels' => $assignment?->isCancelled() ?? false,
        ];
    }

    /**
     * One page of the user's payments, newest first.
     *
     * @return LengthAwarePaginator<int, array{id: string, issued_at: string, package_name: ?string, billing_period: ?string, amount: int, status: string, invoice_url: ?string}>
     */
    public function paymentsFor(User $user): LengthAwarePaginator
    {
        return $user->payments()->with('package')->latest('issued_at')
            ->paginate(10, pageName: 'payments_page')->withQueryString()
            ->through(fn (Payment $payment) => [
                'id' => $payment->id,
                'issued_at' => $payment->issued_at->toIso8601String(),
                'package_name' => $payment->package?->name,
                'billing_period' => $payment->billing_period?->value,
                'amount' => $payment->amount,
                'status' => $payment->status->value,
                'invoice_url' => $payment->invoice_url,
            ]);
    }

    /**
     * One page of the user's package history, newest first.
     *
     * @return LengthAwarePaginator<int, array{id: string, package_name: string, billing_period: string, price: int, starts_at: ?string, expires_at: ?string, status: string}>
     */
    public function assignmentsFor(User $user): LengthAwarePaginator
    {
        $page = PackageAssignment::with(['package', 'subscription'])->where('user_id', $user->id)->latest('starts_at')
            ->paginate(10, pageName: 'history_page')->withQueryString()
            ->through(fn (PackageAssignment $assignment) => [
                'id' => $assignment->id,
                'package_name' => $assignment->package->name,
                'billing_period' => $assignment->billing_period->value,
                'price' => $assignment->price,
                'starts_at' => $assignment->starts_at->toIso8601String(),
                'expires_at' => $assignment->expires_at->toIso8601String(),
                'status' => match (true) {
                    $assignment->starts_at->isFuture() => 'queued',
                    ! $assignment->expires_at->isFuture() => 'expired',
                    $assignment->isCancelled() => 'cancelled',
                    default => 'active',
                },
            ]);

        if ($page->currentPage() === 1) {
            $page->setCollection($this->awaitingActivationFor($user)->concat($page->getCollection()));
        }

        return $page;
    }

    /**
     * Packages invoiced but not paid yet, so not activated; they start when the current package ends, else when bought.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function awaitingActivationFor(User $user): Collection
    {
        $current = $user->activeAssignment();
        $invoiced = $user->payments()->with('package')->where('status', PaymentStatus::Open)->whereNotNull('package_id')->latest('issued_at')->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'package_name' => $payment->package->name,
                'billing_period' => $payment->billing_period?->value,
                'price' => $payment->amount,
                'starts_at' => ($current?->expires_at ?? $payment->issued_at)->toIso8601String(),
                'expires_at' => null,
                'status' => 'awaiting_payment',
            ]);

        $activated = PackageAssignment::where('user_id', $user->id)->whereNotNull('subscription_id')->pluck('subscription_id');
        $subscribed = $user->subscriptions()->whereNotIn('id', $activated)->whereNull('ends_at')->latest()->get()
            ->map(fn (Subscription $subscription) => [$subscription, Package::findByStripePrice((string) $subscription->stripe_price)])
            ->filter(fn (array $pair) => $pair[1] !== null && $invoiced->doesntContain('package_name', $pair[1][0]->name))
            ->map(fn (array $pair) => [
                'id' => $pair[0]->id,
                'package_name' => $pair[1][0]->name,
                'billing_period' => $pair[1][1]->value,
                'price' => $pair[1][0]->priceFor($pair[1][1]),
                'starts_at' => ($current?->expires_at ?? $pair[0]->created_at)->toIso8601String(),
                'expires_at' => null,
                'status' => 'awaiting_payment',
            ]);

        return $invoiced->concat($subscribed)->values();
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
