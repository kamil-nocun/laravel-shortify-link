<?php

namespace Tests\Support;

use App\Models\Domain;
use App\Support\DomainVerifier;

class FakeDomainVerifier implements DomainVerifier
{
    /**
     * Ids of the domains treated as verified.
     *
     * @var list<string>
     */
    public array $verified = [];

    public function hasToken(Domain $domain): bool
    {
        return in_array($domain->id, $this->verified, true);
    }
}
