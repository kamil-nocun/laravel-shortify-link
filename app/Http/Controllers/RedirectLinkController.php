<?php

namespace App\Http\Controllers;

use App\Enums\LinkEntry;
use App\Enums\LinkSource;
use App\Jobs\RecordLinkClick;
use App\Support\ClientDetector;
use App\Support\GeoLocator;
use App\Support\LinkResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RedirectLinkController extends Controller
{
    /**
     * Send the visitor to the target of an active short link and record the click; the IP and User-Agent are only used for the lookups here and are never queued or stored.
     */
    public function __invoke(Request $request, LinkResolver $resolver, GeoLocator $geoLocator, ClientDetector $clientDetector, string $code): RedirectResponse
    {
        $link = $resolver->resolve($request->getHost(), $code) ?? abort(404);

        RecordLinkClick::dispatch(
            $link->id,
            now(),
            rescue(fn () => $geoLocator->locate((string) $request->ip()), GeoLocator::UNKNOWN),
            rescue(fn () => $clientDetector->detect($request->userAgent()), ClientDetector::UNKNOWN),
            $link->code === $code ? LinkEntry::Code : LinkEntry::Alias,
            $request->boolean('qr') ? LinkSource::Qr : LinkSource::Link,
        );

        return redirect()->away($link->original_url);
    }
}
