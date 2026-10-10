<?php

namespace App\Jobs;

use App\Enums\LinkEntry;
use App\Enums\LinkSource;
use App\Models\Link;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordLinkClick implements ShouldQueue
{
    use Queueable;

    /**
     * Only derived values are queued; the visitor's IP and User-Agent never reach the queue tables.
     *
     * @param  array{country_code: ?string, country: ?string, city: ?string}  $location
     * @param  array{browser: ?string, os: ?string, device_type: ?string}  $client
     */
    public function __construct(
        public string $linkId,
        public CarbonInterface $clickedAt,
        public array $location,
        public array $client,
        public LinkEntry $entry = LinkEntry::Code,
        public LinkSource $source = LinkSource::Link,
    ) {}

    /**
     * Store the click; skip it when the link was deleted meanwhile.
     */
    public function handle(): void
    {
        $link = Link::find($this->linkId);

        if ($link === null) {
            return;
        }

        $link->clicks()->create([
            ...$this->location,
            ...$this->client,
            'clicked_at' => $this->clickedAt,
            'entry' => $this->entry->value,
            'source' => $this->source->value,
        ]);
    }
}
