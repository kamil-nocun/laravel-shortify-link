<?php

namespace App\Http\Controllers;

use App\Enums\LinkEntry;
use App\Enums\LinkSource;
use App\Jobs\RecordLinkClick;
use App\Support\LinkResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RedirectLinkController extends Controller
{
    /**
     * Send the visitor to the target of an active short link and record the click.
     */
    public function __invoke(Request $request, LinkResolver $resolver, string $code): RedirectResponse
    {
        $link = $resolver->resolve($request->getHost(), $code) ?? abort(404);

        RecordLinkClick::dispatch(
            $link->id,
            (string) $request->ip(),
            now(),
            $request->userAgent(),
            $link->code === $code ? LinkEntry::Code : LinkEntry::Alias,
            $request->boolean('qr') ? LinkSource::Qr : LinkSource::Link,
        );

        return redirect()->away($link->original_url);
    }
}
