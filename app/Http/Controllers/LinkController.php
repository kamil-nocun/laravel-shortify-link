<?php

namespace App\Http\Controllers;

use App\Actions\CreateLink;
use App\Billing\PackagePageData;
use App\Http\Requests\Links\IndexLinksRequest;
use App\Http\Requests\Links\StoreLinkRequest;
use App\Http\Requests\Links\UpdateLinkRequest;
use App\Models\Link;
use App\Support\MainHost;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LinkController extends Controller
{
    /**
     * List the current user's links.
     */
    public function index(IndexLinksRequest $request, PackagePageData $packageData): Response
    {
        $filters = $request->safe()->only(['search', 'status', 'domain']) + ['search' => null, 'status' => null, 'domain' => null];

        $links = $request->user()->links()->with('domain')->withClickTotal()->latest()->filteredBy($filters);

        return Inertia::render('links/Index', [
            'links' => $links->paginate(15)->withQueryString(),
            'filters' => $filters,
            'domains' => $request->user()->domains()->get(['id', 'host']),
            'packageNotice' => $packageData->withdrawnPackageNotice($request->user()),
            'quota' => ['used' => $request->user()->links()->count(), 'limit' => $request->user()->linkLimit()],
        ]);
    }

    /**
     * Show the form for creating a link.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Link::class);

        return Inertia::render('links/Create', ['domains' => $request->user()->domains()->active()->get(['id', 'host']), 'canSetAlias' => $request->user()->allowsCustomAlias(), 'mainHost' => MainHost::withPort()]);
    }

    /**
     * Store a new link with a generated code.
     */
    public function store(StoreLinkRequest $request, CreateLink $createLink): RedirectResponse
    {
        $createLink($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Link created.')]);

        return to_route('links.index');
    }

    /**
     * Show the form for editing a link.
     */
    public function edit(Link $link): Response
    {
        Gate::authorize('update', $link);

        return Inertia::render('links/Edit', ['link' => $link, 'canSetAlias' => $link->user->allowsCustomAlias(), 'mainHost' => MainHost::withPort()]);
    }

    /**
     * Update a link; its code never changes.
     */
    public function update(UpdateLinkRequest $request, Link $link): RedirectResponse
    {
        try {
            $link->update($request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['alias' => __('This alias is already taken.')]);
        }

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
