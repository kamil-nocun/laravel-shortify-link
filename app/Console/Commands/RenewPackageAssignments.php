<?php

namespace App\Console\Commands;

use App\Enums\BillingPeriod;
use App\Models\Package;
use App\Models\PackageAssignment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Description('Start the next billing period of expired recurring package assignments with the current package terms')]
class RenewPackageAssignments extends Command
{
    /**
     * @var string
     */
    protected $signature = 'packages:renew';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        PackageAssignment::query()
            ->where('billing_period', '!=', BillingPeriod::OneTime)
            ->where('expires_at', '<=', now())
            ->pluck('id')
            ->each(fn (string $id) => DB::transaction(fn () => $this->renew($id)));

        Package::onlyTrashed()->whereDoesntHave('assignments')->forceDelete();

        return self::SUCCESS;
    }

    /**
     * Renew one assignment, or end it when its package is withdrawn or no longer sells the period.
     */
    private function renew(string $id): void
    {
        $assignment = PackageAssignment::with('package')->lockForUpdate()->find($id);

        if ($assignment === null || $assignment->expires_at->isFuture()) {
            return;
        }

        $period = $assignment->billing_period;
        $package = $assignment->package;
        $price = $package->trashed() ? null : $package->priceFor($period);

        if ($price === null) {
            $assignment->delete();

            return;
        }

        $startsAt = $assignment->expires_at;
        // ponytail: month-end drift (Jan 31 -> Feb 28 -> Mar 28); anchor to the original day if it matters.
        while (($expiresAt = $period->expiresAt($startsAt))->lessThanOrEqualTo(now())) {
            $startsAt = $expiresAt;
        }

        $assignment->update([
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'price' => $price,
            'link_limit' => $package->link_limit,
        ]);
    }
}
