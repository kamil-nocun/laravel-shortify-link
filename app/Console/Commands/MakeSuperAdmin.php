<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Description('Promote an existing user, or create the account, as the single super admin')]
class MakeSuperAdmin extends Command
{
    protected $signature = 'user:make-super-admin {email}';

    public function handle(): int
    {
        $email = $this->argument('email');
        $attributes = null;

        if (! User::where('email', $email)->exists()) {
            $validator = Validator::make([
                'email' => $email,
                'name' => $this->ask('Name'),
                'password' => $this->secret('Password'),
            ], [
                'email' => 'required|email|max:255',
                'name' => 'required|string|max:255',
                'password' => ['required', Password::defaults()],
            ]);

            if ($validator->fails()) {
                $this->error($validator->errors()->first());

                return self::FAILURE;
            }

            $attributes = $validator->validated();
        }

        return DB::transaction(function () use ($email, $attributes): int {
            if (User::where('role', UserRole::SuperAdmin)->lockForUpdate()->exists()) {
                $this->error('A super admin already exists.');

                return self::FAILURE;
            }

            $user = User::where('email', $email)->first();

            if ($user === null) {
                $user = new User($attributes);
                $user->forceFill(['email_verified_at' => now()]);
            }

            $user->forceFill(['role' => UserRole::SuperAdmin, 'blocked_at' => null, 'blocked_by' => null])->save();
            $this->info("{$user->email} is now the super admin.");

            return self::SUCCESS;
        });
    }
}
