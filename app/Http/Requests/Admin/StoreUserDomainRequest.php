<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Domains\StoreDomainRequest;

class StoreUserDomainRequest extends StoreDomainRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createDomain', $this->route('user'));
    }
}
