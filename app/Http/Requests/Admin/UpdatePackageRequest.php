<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Validator;

class UpdatePackageRequest extends StorePackageRequest
{
    /**
     * The default package stays default until another one takes over.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->route('package')->is_default && ! $this->boolean('is_default')) {
                $validator->errors()->add('is_default', __('Mark another package as default instead.'));
            }
        }];
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('package'));
    }
}
