<?php

namespace App\Support;

use App\Models\Domain;

class DnsDomainVerifier implements DomainVerifier
{
    public function hasToken(Domain $domain): bool
    {
        $records = @dns_get_record($domain->txtName(), DNS_TXT);

        return is_array($records) && $this->matches($records, $domain);
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    public function matches(array $records, Domain $domain): bool
    {
        $expected = $domain->txtValue();

        foreach ($records as $record) {
            if (($record['txt'] ?? null) === $expected) {
                return true;
            }
        }

        return false;
    }
}
