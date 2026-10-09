<?php

namespace App\Jobs;

use App\Models\Link;
use App\Support\GeoLocator;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordLinkClick implements ShouldQueue
{
    use Queueable;

    /**
     * The IP is only used for the lookup and is never stored with the click.
     */
    public function __construct(
        public string $linkId,
        public string $ip,
        public CarbonInterface $clickedAt,
    ) {}

    /**
     * Store the click with the location of the visitor; skip it when the link was deleted meanwhile.
     */
    public function handle(GeoLocator $geoLocator): void
    {
        $link = Link::find($this->linkId);

        if ($link === null) {
            return;
        }

        $link->clicks()->create([
            ...$geoLocator->locate($this->ip),
            'clicked_at' => $this->clickedAt,
        ]);
    }
}
