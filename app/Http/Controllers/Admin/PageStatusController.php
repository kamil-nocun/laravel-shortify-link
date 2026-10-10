<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePageStatusRequest;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PageStatusController extends Controller
{
    /**
     * Publish or unpublish a page; a page without a version cannot be published.
     */
    public function __invoke(UpdatePageStatusRequest $request, Page $page): RedirectResponse
    {
        $status = PageStatus::from($request->validated('status'));

        if ($status === PageStatus::Published && $page->current_version_id === null) {
            throw ValidationException::withMessages(['status' => __('A page without content cannot be published.')]);
        }

        $page->update(['status' => $status]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $status === PageStatus::Published ? __('Page published.') : __('Page unpublished.')]);

        return back();
    }
}
