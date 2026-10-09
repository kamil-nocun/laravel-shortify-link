<?php

namespace App\Http\Requests\Billing;

use App\Enums\BillingPeriod;
use App\Models\Package;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()->isSuperAdmin();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'package_id' => ['required', 'uuid', Rule::exists('packages', 'id')->where('is_default', false)->whereNull('deleted_at')],
            'billing_period' => ['required', Rule::enum(BillingPeriod::class)],
        ];
    }

    /**
     * The package must sell the period in Stripe, and the user must not already hold a package.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->user()->activeAssignment() !== null) {
                $validator->errors()->add('package', __('You already have a package.'));

                return;
            }

            $period = BillingPeriod::tryFrom((string) $this->input('billing_period'));
            $package = Package::find($this->input('package_id'));

            if ($period && $package && $package->stripePriceId($period) === null) {
                $validator->errors()->add('billing_period', __('This package is not sold for the chosen period.'));
            }
        }];
    }
}
