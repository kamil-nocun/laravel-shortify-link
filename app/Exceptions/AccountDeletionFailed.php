<?php

namespace App\Exceptions;

use RuntimeException;

class AccountDeletionFailed extends RuntimeException
{
    /**
     * Message safe to show to the user.
     */
    public function userMessage(): string
    {
        return __('Could not delete the account right now, try again later.');
    }
}
