<?php

namespace App\Console\Commands;

use App\Billing\PackageBilling;
use App\Billing\RecordInvoicePayment;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;

use function Laravel\Prompts\progress;

#[Description('Store the Stripe invoices of every customer in the payment history')]
class BackfillPayments extends Command
{
    protected $signature = 'payments:backfill';

    public function handle(PackageBilling $billing, RecordInvoicePayment $recorder): int
    {
        $stored = 0;
        $customers = User::whereNotNull('stripe_id');
        $progress = progress('Backfilling invoices', $customers->count());
        $progress->start();

        $customers->each(function (User $user) use ($billing, $recorder, $progress, &$stored) {
            foreach ($billing->invoicesFor($user) as $invoice) {
                $stored += $recorder->fromInvoice($invoice, $invoice['created']) === null ? 0 : 1;
            }

            $progress->advance();
        });

        $progress->finish();

        $this->info("Stored {$stored} invoices.");

        return self::SUCCESS;
    }
}
