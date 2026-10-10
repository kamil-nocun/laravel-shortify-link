<?php

namespace App\Jobs;

use App\Enums\LinkEntry;
use App\Enums\LinkSource;
use App\Models\Link;
use App\Support\ClientDetector;
use App\Support\GeoLocator;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordLinkClick implements ShouldQueue
{
    use Queueable;

    /**
     * The IP and User-Agent are only used for lookups and are never stored with the click.
     */
    public function __construct(
        public string $linkId,
        public string $ip,
        public CarbonInterface $clickedAt,
        public ?string $userAgent = null,
        public LinkEntry $entry = LinkEntry::Code,
        public LinkSource $source = LinkSource::Link,
    ) {}

    /**
     * Store the click with the location of the visitor; skip it when the link was deleted meanwhile.
     */
    public function handle(GeoLocator $geoLocator, ClientDetector $clientDetector): void
    {
        $link = Link::find($this->linkId);

        if ($link === null) {
            return;
        }

        $link->clicks()->create([
            ...$geoLocator->locate($this->ip),
            ...$clientDetector->detect($this->userAgent),
            'clicked_at' => $this->clickedAt,
            'entry' => $this->entry->value,
            'source' => $this->source->value,
        ]);
    }
}
