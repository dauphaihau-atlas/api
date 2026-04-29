<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\TenantModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_deterministic_export_demo_users(): void
    {
        $this->seed();

        $defaultTenant = TenantModel::query()
            ->where('slug', 'default')
            ->firstOrFail();

        $seededUsers = UserModel::query()
            ->where('tenant_id', $defaultTenant->id)
            ->orderBy('email')
            ->pluck('created_at', 'email')
            ->map(fn ($createdAt) => $createdAt->format('Y-m-d H:i:s'))
            ->all();

        $this->assertSame([
            'export-autumn@example.com' => '2024-10-05 08:15:00',
            'export-boundary-end@example.com' => '2024-12-31 23:59:59',
            'export-boundary-start@example.com' => '2024-01-01 00:00:00',
            'export-midyear@example.com' => '2024-06-15 12:00:00',
            'export-new-year@example.com' => '2025-01-01 00:00:00',
            'export-spring@example.com' => '2024-04-10 14:00:00',
            'export-summer@example.com' => '2024-08-20 16:45:00',
            'export-winter@example.com' => '2024-02-15 09:30:00',
            'maya.chen@arc.test' => '2024-03-01 09:00:00',
        ], $seededUsers);
    }

    public function test_database_seeder_is_safe_to_run_multiple_times(): void
    {
        $this->seed();
        $this->seed();

        $defaultTenant = TenantModel::query()
            ->where('slug', 'default')
            ->firstOrFail();

        $this->assertSame(1, TenantModel::query()->where('slug', 'default')->count());
        $this->assertSame(10, UserModel::query()->count());
        $this->assertSame(9, UserModel::query()->where('tenant_id', $defaultTenant->id)->count());
        $this->assertSame(1, UserModel::query()->where('email', 'superadmin@example.com')->count());
    }

    public function test_database_seeder_has_expected_user_roles(): void
    {
        $this->seed();

        $this->assertSame([
            'admin',
            'super_admin',
            'support',
            'tenant_owner',
            'user',
            'viewer',
        ], RoleModel::query()->orderBy('slug')->pluck('slug')->all());
    }
}
