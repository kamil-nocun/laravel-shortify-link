<?php

namespace App\Http\Controllers;

use App\Models\Link;
use Illuminate\Http\RedirectResponse;

class RedirectLinkController extends Controller
{
    /**
     * Send the visitor to the target of an active short link.
     */
    public function __invoke(string $code): RedirectResponse
    {
        $link = Link::active()->where('code', $code)->firstOrFail();

        return redirect()->away($link->original_url);
    }
}
