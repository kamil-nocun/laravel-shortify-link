<?php

namespace App\Support;

use App\Models\Domain;
use App\Models\Link;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserConsent;
use Carbon\CarbonInterface;

class AccountExport
{
    public function __construct(private readonly CsvWriter $csv) {}

    /**
     * Everything stored about the account, as CSV file name => content.
     *
     * @return array<string, string>
     */
    public function files(User $user): array
    {
        return [
            'profile.csv' => $this->csv->render(
                ['name', 'email', 'role', 'package', 'created_at', 'email_verified_at'],
                [[$user->name, $user->email, $user->role?->name, $user->package()?->name, $this->date($user->created_at), $this->date($user->email_verified_at)]],
            ),
            'links.csv' => $this->csv->render(
                ['title', 'code', 'alias', 'original_url', 'domain', 'active', 'expires_at', 'created_at', 'clicks'],
                $user->links()->with('domain')->withClickTotal()->orderBy('created_at')->get()->map(fn (Link $link): array => [
                    $link->title, $link->code, $link->alias, $link->original_url, $link->domain?->host, $link->is_active,
                    $this->date($link->expires_at), $this->date($link->created_at), $link->clicks_count,
                ]),
            ),
            'domains.csv' => $this->csv->render(
                ['host', 'verified_at', 'created_at'],
                $user->domains()->orderBy('created_at')->get()->map(fn (Domain $domain): array => [
                    $domain->host, $this->date($domain->verified_at), $this->date($domain->created_at),
                ]),
            ),
            'payments.csv' => $this->csv->render(
                ['invoice', 'billing_period', 'status', 'amount', 'currency', 'issued_at', 'paid_at'],
                $user->payments()->orderBy('issued_at')->get()->map(fn (Payment $payment): array => [
                    $payment->stripe_invoice_id, $payment->billing_period?->value, $payment->status->value,
                    number_format($payment->amount / 100, 2, '.', ''), $payment->currency,
                    $this->date($payment->issued_at), $this->date($payment->paid_at),
                ]),
            ),
            'consents.csv' => $this->csv->render(
                ['document', 'slug', 'version_id', 'accepted_at'],
                $user->consents()->with('pageVersion.page')->orderBy('accepted_at')->get()->map(fn (UserConsent $consent): array => [
                    $consent->pageVersion->title, $consent->pageVersion->page->slug, $consent->page_version_id, $this->date($consent->accepted_at),
                ]),
            ),
        ];
    }

    private function date(?CarbonInterface $date): ?string
    {
        return $date?->toIso8601String();
    }
}
