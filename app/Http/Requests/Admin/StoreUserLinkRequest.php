<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Links\StoreLinkRequest;
use App\Models\User;

class StoreUserLinkRequest extends StoreLinkRequest
{
    protected function linkOwner(): User
    {
        return $this->route('user');
    }

    public function authorize(): bool
    {
        return $this->user()->can('createLink', $this->route('user'));
    }
}
