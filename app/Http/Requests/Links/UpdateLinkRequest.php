<?php

namespace App\Http\Requests\Links;

use App\Models\Link;
use App\Models\User;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;
use Throwable;

class UpdateLinkRequest extends StoreLinkRequest
{
    /**
     * Only the link owner may update it.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('link'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['domain_id']);

        if (! $this->linkOwner()->allowsCustomAlias()) {
            unset($rules['alias']);
        }

        if ($this->expiresAtIsUnchanged()) {
            $rules['expires_at'] = ['nullable', 'date'];
        }

        return $rules;
    }

    /**
     * Without the permission the alias may only be left as it is.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->linkOwner()->allowsCustomAlias() || ! $this->has('alias')) {
                return;
            }

            if (($this->input('alias') ?: null) !== $this->route('link')->alias) {
                $validator->errors()->add('alias', __('Your package does not include custom aliases.'));
            }
        }];
    }

    protected function linkOwner(): User
    {
        return $this->route('link')->user;
    }

    protected function aliasDomainId(): ?string
    {
        return $this->route('link')->domain_id;
    }

    protected function ignoredLinkId(): ?string
    {
        return $this->route('link')->id;
    }

    private function expiresAtIsUnchanged(): bool
    {
        /** @var Link $link */
        $link = $this->route('link');

        try {
            return $link->expires_at !== null
                && $link->expires_at->copy()->startOfMinute()->equalTo(Carbon::parse((string) $this->input('expires_at'))->startOfMinute());
        } catch (Throwable) {
            return false;
        }
    }
}
