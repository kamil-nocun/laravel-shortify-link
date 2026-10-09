<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Links\LinkStatsRequest;

class AdminStatsRequest extends LinkStatsRequest
{
    /**
     * Access is guarded by the admin middleware.
     */
    public function authorize(): bool
    {
        return true;
    }
}
