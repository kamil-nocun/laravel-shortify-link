<?php

namespace App\Actions;

use App\Models\Link;
use App\Models\User;
use App\Support\LinkCodeGenerator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class CreateLink
{
    public function __construct(private readonly LinkCodeGenerator $generator) {}

    /**
     * Create a link for the user with a generated code and the optional alias, retrying once on a race-condition collision.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(User $user, array $attributes): Link
    {
        $domainId = $attributes['domain_id'] ?? null;
        $alias = $attributes['alias'] ?? null;
        $create = fn () => $user->links()->create([...$attributes, 'code' => $this->generator->generate($domainId)]);

        try {
            return $create();
        } catch (UniqueConstraintViolationException) {
            if ($alias !== null && Link::isCodeOrAliasTaken($domainId, $alias)) {
                throw ValidationException::withMessages(['alias' => __('This alias is already taken.')]);
            }

            return $create();
        }
    }
}
