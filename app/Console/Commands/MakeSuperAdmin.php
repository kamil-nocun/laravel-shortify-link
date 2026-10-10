<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Description('Promote an existing user, or create the account, as the single super admin')]
class MakeSuperAdmin extends Command
{
    protected $signature = 'user:make-super-admin {email? : Email of the account; asked for when omitted}';

    public function handle(): int
    {
        if ($this->superAdminExists()) {
            $this->error('A super admin already exists.');

            return self::FAILURE;
        }

        $email = $this->argument('email') ?? text('Email', required: true, validate: fn (string $value): ?string => $this->firstError('email', $value));

        if (($error = $this->firstError('email', $email)) !== null) {
            $this->error($error);

            return self::FAILURE;
        }

        $attributes = null;

        if (User::where('email', $email)->exists()) {
            if (! confirm("Promote {$email} to the single super admin?")) {
                $this->warn('Aborted.');

                return self::FAILURE;
            }
        } else {
            $attributes = [
                'email' => $email,
                'name' => text('Name', required: true, validate: fn (string $value): ?string => $this->firstError('name', $value)),
                'password' => password('Password', required: true, validate: fn (string $value): ?string => $this->firstError('password', $value)),
            ];
        }

        return DB::transaction(function () use ($email, $attributes): int {
            if ($this->superAdminExists(lock: true)) {
                $this->error('A super admin already exists.');

                return self::FAILURE;
            }

            $user = User::where('email', $email)->first();

            if ($user === null) {
                $user = new User($attributes);
                $user->forceFill(['email_verified_at' => now()]);
            }

            $user->forceFill(['role_id' => Role::where('is_super', true)->value('id'), 'blocked_at' => null, 'blocked_by' => null])->save();
            $this->info("{$user->email} is now the super admin.");

            return self::SUCCESS;
        });
    }

    /**
     * Whether the single super admin account is already taken; locked rows serialise concurrent promotions.
     */
    private function superAdminExists(bool $lock = false): bool
    {
        $query = User::whereHas('role', fn ($query) => $query->where('is_super', true));

        return ($lock ? $query->lockForUpdate() : $query)->exists();
    }

    /**
     * First validation error of one account field, or null when the value is valid.
     */
    private function firstError(string $field, string $value): ?string
    {
        $rules = [
            'email' => 'required|email|max:255',
            'name' => 'required|string|max:255',
            'password' => ['required', Password::defaults()],
        ];

        $validator = Validator::make([$field => $value], [$field => $rules[$field]]);

        return $validator->fails() ? $validator->errors()->first() : null;
    }
}
