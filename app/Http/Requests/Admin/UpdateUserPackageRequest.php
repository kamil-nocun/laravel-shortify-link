<?php

namespace App\Http\Requests\Admin;

use App\Enums\BillingPeriod;
use App\Models\Package;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignPackage', $this->route('user'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'package_id' => ['required', 'uuid', Rule::exists('packages', 'id')],
            'billing_period' => ['required', Rule::enum(BillingPeriod::class)],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => [
                Rule::requiredIf($this->input('billing_period') === BillingPeriod::OneTime->value),
                'nullable',
                'date',
                'after:'.($this->filled('starts_at') ? 'starts_at' : 'now'),
            ],
        ];
    }

    /**
     * The chosen period must have a price in the package.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $period = BillingPeriod::tryFrom((string) $this->input('billing_period'));
            $package = Package::find($this->input('package_id'));

            if ($period && $package && $package->priceFor($period) === null) {
                $validator->errors()->add('billing_period', __('This package has no price for the chosen period.'));
            }
        }];
    }
}
