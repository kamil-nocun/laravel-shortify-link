<?php

namespace App\Http\Requests\Links;

use App\Models\Link;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

class UpdateLinkRequest extends StoreLinkRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        if ($this->expiresAtIsUnchanged()) {
            $rules['expires_at'] = ['nullable', 'date'];
        }

        return $rules;
    }

    private function expiresAtIsUnchanged(): bool
    {
        /** @var Link $link */
        $link = $this->route('link');

        try {
            return $link->expires_at !== null
                && $link->expires_at->equalTo(Carbon::parse((string) $this->input('expires_at')));
        } catch (Throwable) {
            return false;
        }
    }
}
