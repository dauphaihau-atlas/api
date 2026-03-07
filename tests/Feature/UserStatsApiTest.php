<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class UserStatsApiTest extends TestCase
{
    use RefreshDatabase;

    // ── Access control ──

    public function test_admin_can_get_stats(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->getJson('/api/v1/users/stats', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['total_active', 'total_deleted', 'created_today']]);
    }

    public function test_non_admin_cannot_get_stats(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'token' => $token] = $this->createTenantWithUser();

        $response = $this->getJson('/api/v1/users/stats', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_get_stats(): void
    {
        $response = $this->getJson('/api/v1/users/stats');

        $response->assertStatus(401);
    }

    // ── Correctness ──

    public function test_total_active_reflects_non_deleted_users(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        UserModel::factory()->count(3)->forTenant($tenant)->create();

        $response = $this->getJson('/api/v1/users/stats', $this->tenantHeaders($tenant, $token));

        // admin + 3 users = 4 active
        $response->assertStatus(200);
        $response->assertJsonPath('data.total_active', 4);
        $response->assertJsonPath('data.total_deleted', 0);
    }

    public function test_total_active_excludes_soft_deleted_users(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        $user = UserModel::factory()->forTenant($tenant)->create();
        $user->delete();

        $response = $this->getJson('/api/v1/users/stats', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_active', 1);
        $response->assertJsonPath('data.total_deleted', 1);
    }

    public function test_total_deleted_counts_only_soft_deleted_users(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        UserModel::factory()->count(2)->forTenant($tenant)->create();
        $deleted1 = UserModel::factory()->forTenant($tenant)->create();
        $deleted2 = UserModel::factory()->forTenant($tenant)->create();
        $deleted1->delete();
        $deleted2->delete();

        $response = $this->getJson('/api/v1/users/stats', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_active', 3); // admin + 2 active
        $response->assertJsonPath('data.total_deleted', 2);
    }

    public function test_created_today_counts_only_todays_users(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        UserModel::factory()->count(2)->forTenant($tenant)->create();
        // Simulate a user created yesterday
        UserModel::factory()->forTenant($tenant)->create(['created_at' => now()->subDay()]);

        $response = $this->getJson('/api/v1/users/stats', $this->tenantHeaders($tenant, $token));

        // admin + 2 today users = 3 created today; yesterday user excluded
        $response->assertStatus(200);
        $response->assertJsonPath('data.created_today', 3);
    }

    // ── Cache invalidation ──

    public function test_stats_cache_is_invalidated_when_user_is_created(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        // Prime the cache
        $first = $this->getJson('/api/v1/users/stats', $this->tenantHeaders($tenant, $token));
        $first->assertStatus(200);
        $this->assertSame(1, $first->json('data.total_active'));

        // Creating a user should flush the cache
        UserModel::factory()->forTenant($tenant)->create();

        // Cache should reflect updated count
        $second = $this->getJson('/api/v1/users/stats', $this->tenantHeaders($tenant, $token));
        $second->assertStatus(200);
        $this->assertSame(2, $second->json('data.total_active'));
    }

    public function test_stats_cache_is_invalidated_when_user_is_soft_deleted(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        $user = UserModel::factory()->forTenant($tenant)->create();

        // Prime the cache
        $first = $this->getJson('/api/v1/users/stats', $this->tenantHeaders($tenant, $token));
        $first->assertStatus(200);
        $this->assertSame(2, $first->json('data.total_active'));
        $this->assertSame(0, $first->json('data.total_deleted'));

        // Soft-deleting a user should flush the cache
        $user->delete();

        $second = $this->getJson('/api/v1/users/stats', $this->tenantHeaders($tenant, $token));
        $second->assertStatus(200);
        $this->assertSame(1, $second->json('data.total_active'));
        $this->assertSame(1, $second->json('data.total_deleted'));
    }

    public function test_stats_cache_is_invalidated_when_user_is_restored(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        $user = UserModel::factory()->forTenant($tenant)->create();
        $user->delete();

        // Prime the cache (only admin active)
        $first = $this->getJson('/api/v1/users/stats', $this->tenantHeaders($tenant, $token));
        $first->assertStatus(200);
        $this->assertSame(1, $first->json('data.total_active'));
        $this->assertSame(1, $first->json('data.total_deleted'));

        // Restoring should flush the cache
        $user->restore();

        $second = $this->getJson('/api/v1/users/stats', $this->tenantHeaders($tenant, $token));
        $second->assertStatus(200);
        $this->assertSame(2, $second->json('data.total_active'));
        $this->assertSame(0, $second->json('data.total_deleted'));
    }

    public function test_stats_cache_is_invalidated_when_user_is_force_deleted(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        $user = UserModel::factory()->forTenant($tenant)->create();

        // Prime the cache
        $first = $this->getJson('/api/v1/users/stats', $this->tenantHeaders($tenant, $token));
        $first->assertStatus(200);
        $this->assertSame(2, $first->json('data.total_active'));

        // Force-deleting should flush the cache
        $user->forceDelete();

        $second = $this->getJson('/api/v1/users/stats', $this->tenantHeaders($tenant, $token));
        $second->assertStatus(200);
        $this->assertSame(1, $second->json('data.total_active'));
    }

    // ── Export last cache ──

    public function test_last_export_returns_null_when_no_export_cached(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->getJson('/api/v1/users/export/last', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $response->assertJsonPath('data', null);
    }

    public function test_last_export_returns_cached_metadata(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        $metadata = [
            'path' => 'exports/users-test.csv',
            'url' => 'http://localhost/download',
            'expires_at' => now()->addMinutes(15)->format('c'),
        ];
        Cache::put("exports:users:last:{$admin->id}", $metadata, 900);

        $response = $this->getJson('/api/v1/users/export/last', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $response->assertJsonPath('data.path', $metadata['path']);
        $response->assertJsonPath('data.url', $metadata['url']);
    }
}
