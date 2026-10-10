<?php

namespace App\Console\Commands;

use App\Models\PackageAssignment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('domains:backfill-limits')]
#[Description('Give assignments without a domain limit the limit of their package')]
class BackfillDomainLimits extends Command
{
    public function handle(): int
    {
        $updated = PackageAssignment::query()
            ->join('packages', 'packages.id', '=', 'package_assignments.package_id')
            ->where('package_assignments.domain_limit', 0)
            ->where('packages.domain_limit', '>', 0)
            ->update(['package_assignments.domain_limit' => DB::raw('packages.domain_limit')]);

        $this->info("Updated {$updated} assignments.");

        return self::SUCCESS;
    }
}
