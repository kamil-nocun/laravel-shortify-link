<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Links\StoreLinkRequest;

class StoreUserLinkRequest extends StoreLinkRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createLink', $this->route('user'));
    }
}
