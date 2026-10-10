<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Support\DomainVerifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class VerifyDomain implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public function __construct(public string $domainId) {}

    /**
     * Check the DNS record; retry every five minutes; an unverified domain is deleted after 72 hours so the host is freed.
     */
    public function handle(DomainVerifier $verifier): void
    {
        $domain = Domain::find($this->domainId);

        if ($domain === null || $domain->isVerified()) {
            return;
        }

        if ($domain->created_at->lt(now()->subHours(72))) {
            $domain->delete();

            return;
        }

        if ($verifier->hasToken($domain)) {
            $domain->markVerified();

            return;
        }

        $this->release(300);
    }
}
