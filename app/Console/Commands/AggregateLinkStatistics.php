<?php

namespace App\Console\Commands;

use App\Actions\AggregateDailyLinkStatistics;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;

#[Description('Collapse the individual clicks of finished days into daily summaries')]
class AggregateLinkStatistics extends Command
{
    protected $signature = 'stats:aggregate';

    public function handle(AggregateDailyLinkStatistics $aggregate): int
    {
        $this->info('Aggregated '.$aggregate().' day(s).');

        return self::SUCCESS;
    }
}
