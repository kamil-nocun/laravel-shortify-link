<?php

namespace App\Http\Requests\Admin;

use App\Models\User;

class StoreAdministratorRequest extends StoreUserRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageAdministrators', User::class);
    }
}
