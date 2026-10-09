<?php

namespace App\Http\Controllers;

use App\Jobs\RecordLinkClick;
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
        $link = Link::active()->where('code', $code)->firstOrFail();

        RecordLinkClick::dispatch($link->id, (string) $request->ip(), now(), $request->userAgent());

        return redirect()->away($link->original_url);
    }
}
