<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn () => ['role_id' => Role::where('name', PermissionCatalog::ADMINISTRATOR_ROLE)->value('id')]);
    }

    /**
     * Indicate that the user is the super admin.
     */
    public function superAdmin(): static
    {
        return $this->state(fn () => ['role_id' => Role::where('name', PermissionCatalog::SUPER_ADMIN_ROLE)->value('id')]);
    }

    /**
     * Assign the created user to the given administrator.
     */
    public function assignedTo(User $admin): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignedAdmins()->attach($admin));
    }

    /**
     * Indicate that the user is blocked.
     */
    public function blocked(): static
    {
        return $this->state(['blocked_at' => now()]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static {}
}
