<?php

namespace App\Support;

final class ReservedAliases
{
    private const WORDS = [
        'admin', 'dashboard', 'links', 'domains', 'billing', 'settings', 'login', 'logout', 'register',
        'forgot-password', 'reset-password', 'verify-email', 'email', 'two-factor-challenge', 'user', 'up',
        'internal', 'api', 'stripe', 'storage', 'build', 'content', 'consent',
    ];

    public static function isReserved(string $alias): bool
    {
        return in_array(strtolower($alias), self::WORDS, true);
    }
}
