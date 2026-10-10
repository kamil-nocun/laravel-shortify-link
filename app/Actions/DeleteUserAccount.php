<?php

namespace App\Actions;

use App\Billing\PackageBilling;
use App\Exceptions\AccountDeletionFailed;
use App\Models\User;
use Throwable;

class DeleteUserAccount
{
    public function __construct(private readonly PackageBilling $billing) {}

    /**
     * Delete the Stripe customer, which also cancels its subscriptions, and then the account with all its data.
     * A failure at Stripe leaves the account untouched.
     *
     * @throws AccountDeletionFailed
     */
    public function __invoke(User $user): void
    {
        try {
            $this->billing->deleteCustomer($user);
        } catch (Throwable $exception) {
            report($exception);

            throw new AccountDeletionFailed(previous: $exception);
        }

        $user->delete();
    }
}
