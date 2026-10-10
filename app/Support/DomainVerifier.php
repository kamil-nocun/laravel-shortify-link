<?php

namespace App\Support;

use App\Models\Domain;

interface DomainVerifier
{
    /**
     * Whether the domain publishes its verification token in the _shortify-verify TXT record.
     */
    public function hasToken(Domain $domain): bool;
}
