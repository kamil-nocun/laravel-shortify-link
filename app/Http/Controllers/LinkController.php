<?php

namespace App\Http\Controllers;

use App\Http\Requests\Links\StoreLinkRequest;
use App\Http\Requests\Links\UpdateLinkRequest;
use App\Models\Link;
use App\Support\LinkCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LinkController extends Controller
{
    /**
     * List the current user's links.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('links/Index', [
            'links' => $request->user()->links()->withCount('clicks')->latest()->paginate(15),
        ]);
    }

    /**
     * Show the form for creating a link.
     */
    public function create(): Response
    {
        return Inertia::render('links/Create');
    }

    /**
     * Store a new link with a generated code.
     */
    public function store(StoreLinkRequest $request, LinkCodeGenerator $generator): RedirectResponse
    {
        $generator->createFor($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Link created.')]);

        return to_route('links.index');
    }

    /**
     * Show the form for editing a link.
     */
    public function edit(Link $link): Response
    {
        Gate::authorize('update', $link);

        return Inertia::render('links/Edit', ['link' => $link]);
    }

    /**
     * Update a link; its code never changes.
     */
    public function update(UpdateLinkRequest $request, Link $link): RedirectResponse
    {
        $link->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Link updated.')]);

        return to_route('links.index');
    }

    /**
     * Delete a link.
     */
    public function destroy(Link $link): RedirectResponse
    {
        Gate::authorize('delete', $link);

        $link->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Link deleted.')]);

        return to_route('links.index');
    }
}
