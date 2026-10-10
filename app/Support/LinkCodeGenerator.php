<?php

namespace App\Support;

use App\Models\Link;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

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
     * Create a link for the user with a generated code and the optional alias, retrying once on a race-condition collision.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createFor(User $user, array $attributes): Link
    {
        $domainId = $attributes['domain_id'] ?? null;
        $alias = $attributes['alias'] ?? null;
        $create = fn () => $user->links()->create([...$attributes, 'code' => $this->generate($domainId)]);

        try {
            return $create();
        } catch (UniqueConstraintViolationException) {
            if ($alias !== null && Link::isCodeOrAliasTaken($domainId, $alias)) {
                throw ValidationException::withMessages(['alias' => __('This alias is already taken.')]);
            }

            return $create();
        }
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
