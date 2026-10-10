<?php

namespace App\Jobs;

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
     * Not promoted: jobs queued before this property existed are restored without it.
     */
    public ?string $userAgent = null;

    /**
     * Not promoted: jobs queued before this property existed are restored without it.
     */
    public ?string $entry = null;

    /**
     * The IP and User-Agent are only used for lookups and are never stored with the click.
     */
    public function __construct(
        public string $linkId,
        public string $ip,
        public CarbonInterface $clickedAt,
        ?string $userAgent = null,
        ?string $entry = null,
    ) {
        $this->userAgent = $userAgent;
        $this->entry = $entry;
    }

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
            'entry' => $this->entry,
        ]);
    }
}
