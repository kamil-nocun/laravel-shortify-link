<?php

namespace App\Http\Controllers;

use App\Models\PageVersion;
use App\Support\ConsentDocuments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConsentController extends Controller
{
    /**
     * Show the documents the user still has to accept.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $pending = ConsentDocuments::pendingFor($request->user());

        if ($pending->isEmpty()) {
            return to_route($request->user()->isAdmin() ? 'admin.dashboard' : 'dashboard');
        }

        return Inertia::render('consent/Show', [
            'documents' => $pending->map(fn (PageVersion $version): array => [
                'id' => $version->id,
                'title' => $version->title,
                'slug' => $version->page->slug,
            ])->all(),
        ]);
    }

    /**
     * Record the acceptance of the current documents.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['versions' => ['nullable', 'array'], 'versions.*' => ['string']]);

        ConsentDocuments::accept($request->user(), $validated['versions'] ?? null);

        return redirect()->intended(route('dashboard'));
    }
}
