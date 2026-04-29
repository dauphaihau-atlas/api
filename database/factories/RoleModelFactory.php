<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Infrastructure\Persistence\Eloquent\Models\RoleModel>
 */
class RoleModelFactory extends Factory
{
    protected $model = RoleModel::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'slug' => fake()->unique()->slug(1),
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Full system access',
        ]);
    }

    public function tenantOwner(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Tenant Owner',
            'slug' => 'tenant_owner',
            'description' => 'Owner-level tenant administration access',
        ]);
    }

    public function support(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Support',
            'slug' => 'support',
            'description' => 'Support access for user assistance and audit review',
        ]);
    }

    public function viewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Viewer',
            'slug' => 'viewer',
            'description' => 'Read-only access',
        ]);
    }

    public function user(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'User',
            'slug' => 'user',
            'description' => 'Standard user access',
        ]);
    }
}
