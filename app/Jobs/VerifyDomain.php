<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Support\DomainVerifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class VerifyDomain implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $domainId) {}

    /**
     * Check the DNS record; retry every five minutes until the domain is 72 hours old.
     */
    public function handle(DomainVerifier $verifier): void
    {
        $domain = Domain::find($this->domainId);

        if ($domain === null || $domain->isVerified() || $domain->created_at->lt(now()->subHours(72))) {
            return;
        }

        if ($verifier->hasToken($domain)) {
            $domain->markVerified();

            return;
        }

        self::dispatch($this->domainId)->delay(now()->addMinutes(5));
    }
}
