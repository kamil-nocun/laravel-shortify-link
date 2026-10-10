<?php

namespace App\Support;

use App\Models\Domain;
use App\Models\Link;

class LinkResolver
{
    /**
     * The active link reachable at the host under the code or alias; null when there is none.
     */
    public function resolve(string $host, string $code): ?Link
    {
        $links = Link::active()->where(fn ($query) => $query->where('code', $code)->orWhere('alias', $code));

        if ($host === MainHost::name()) {
            return $links->whereNull('domain_id')->first();
        }

        $domainId = Domain::active()->where('host', $host)->value('id');

        return $domainId === null ? null : $links->where('domain_id', $domainId)->first();
    }
}
