<?php

namespace App\Support;

use App\Models\Link;

class LinkCodeGenerator
{
    public const MIN_LENGTH = 6;

    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public function __construct(private float $fillThreshold = 0.99) {}

    /**
     * Return a code unused within the domain (null = main domain).
     */
    public function generate(?string $domainId = null): string
    {
        $length = $this->currentLength();

        do {
            $code = $this->randomCode($length);
        } while (Link::isCodeOrAliasTaken($domainId, $code));

        return $code;
    }

    /**
     * Shortest length whose code space is not yet filled past the threshold.
     */
    public function currentLength(): int
    {
        $length = self::MIN_LENGTH;

        while (Link::whereRaw('LENGTH(code) = ?', [$length])->count() >= strlen(self::ALPHABET) ** $length * $this->fillThreshold) {
            $length++;
        }

        return $length;
    }

    protected function randomCode(int $length): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }
}
