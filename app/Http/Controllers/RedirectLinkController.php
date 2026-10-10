<?php

namespace App\Http\Controllers;

use App\Enums\LinkEntry;
use App\Jobs\RecordLinkClick;
use App\Models\Domain;
use App\Models\Link;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RedirectLinkController extends Controller
{
    /**
     * Send the visitor to the target of an active short link and record the click.
     */
    public function __invoke(Request $request, string $code): RedirectResponse
    {
        $mainHost = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));
        $links = Link::active()->where(fn ($query) => $query->where('code', $code)->orWhere('alias', $code));

        if ($request->getHost() === $mainHost) {
            $links->whereNull('domain_id');
        } else {
            $links->where('domain_id', Domain::active()->where('host', $request->getHost())->firstOrFail()->id);
        }

        $link = $links->firstOrFail();

        $entry = $link->code === $code ? LinkEntry::Code : LinkEntry::Alias;

        RecordLinkClick::dispatch($link->id, (string) $request->ip(), now(), $request->userAgent(), $entry->value);

        return redirect()->away($link->original_url);
    }
}
