<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\TenantModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Infrastructure\Persistence\Eloquent\Models\UserModel>
 */
class UserModelFactory extends Factory
{
    protected $model = UserModel::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'version' => 1,
        ];
    }

    public function admin(): static
    {
        return $this->afterCreating(function (UserModel $user) {
            $role = RoleModel::where('slug', 'admin')->firstOrFail();
            $user->roles()->attach($role);
        });
    }

    public function user(): static
    {
        return $this->afterCreating(function (UserModel $user) {
            $role = RoleModel::where('slug', 'user')->firstOrFail();
            $user->roles()->attach($role);
        });
    }

    public function superAdmin(): static
    {
        return $this->afterCreating(function (UserModel $user) {
            $role = RoleModel::where('slug', 'super_admin')->firstOrFail();
            $user->roles()->attach($role);
        });
    }

    public function forTenant(TenantModel $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
