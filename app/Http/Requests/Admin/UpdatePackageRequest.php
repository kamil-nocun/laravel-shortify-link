<?php

namespace App\Http\Requests\Admin;

class UpdatePackageRequest extends StorePackageRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('package'));
    }
}
