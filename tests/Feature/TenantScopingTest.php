<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantScopingTest extends TestCase
{
    use RefreshDatabase;

    // ── User scoping ──

    public function test_tenant_admin_only_sees_own_tenant_users(): void
    {
        ['tenant' => $tenantA, 'admin' => $adminA, 'token' => $tokenA] = $this->createTenantWithAdmin();
        ['tenant' => $tenantB] = $this->createTenantWithAdmin();
        $userA = UserModel::factory()->user()->forTenant($tenantA)->create();
        $userB = UserModel::factory()->user()->forTenant($tenantB)->create();

        $response = $this->getJson('/api/v1/users', $this->tenantHeaders($tenantA, $tokenA));

        $response->assertStatus(200);
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($userA->id, $ids);
        $this->assertNotContains($userB->id, $ids);
    }

    public function test_tenant_admin_cannot_delete_other_tenant_user_by_id(): void
    {
        ['tenant' => $tenantA, 'admin' => $adminA, 'token' => $tokenA] = $this->createTenantWithAdmin();
        ['tenant' => $tenantB] = $this->createTenantWithAdmin();
        $userB = UserModel::factory()->user()->forTenant($tenantB)->create();

        $response = $this->deleteJson('/api/v1/users/'.$userB->id, [], $this->tenantHeaders($tenantA, $tokenA));

        $response->assertStatus(404);
        $this->assertDatabaseHas('users', ['id' => $userB->id, 'deleted_at' => null]);
    }

    public function test_tenant_admin_cannot_restore_other_tenant_user(): void
    {
        ['tenant' => $tenantA, 'admin' => $adminA, 'token' => $tokenA] = $this->createTenantWithAdmin();
        ['tenant' => $tenantB] = $this->createTenantWithAdmin();
        $userB = UserModel::factory()->user()->forTenant($tenantB)->create();
        $userB->delete();

        $response = $this->postJson('/api/v1/users/'.$userB->id.'/restore', [], $this->tenantHeaders($tenantA, $tokenA));

        $response->assertStatus(404);
        $this->assertSoftDeleted('users', ['id' => $userB->id]);
    }

    public function test_tenant_admin_cannot_delete_other_tenant_user(): void
    {
        ['tenant' => $tenantA, 'admin' => $adminA, 'token' => $tokenA] = $this->createTenantWithAdmin();
        ['tenant' => $tenantB] = $this->createTenantWithAdmin();
        $userB = UserModel::factory()->user()->forTenant($tenantB)->create();

        $response = $this->deleteJson('/api/v1/users/'.$userB->id, [], $this->tenantHeaders($tenantA, $tokenA));

        $response->assertStatus(404);
        $this->assertDatabaseHas('users', ['id' => $userB->id, 'deleted_at' => null]);
    }

    // ── Activity log scoping ──

    public function test_activity_logs_are_scoped_to_tenant(): void
    {
        ['tenant' => $tenantA, 'admin' => $adminA, 'token' => $tokenA] = $this->createTenantWithAdmin();
        ['tenant' => $tenantB, 'admin' => $adminB, 'token' => $tokenB] = $this->createTenantWithAdmin();

        // Create a user in Tenant B to generate an activity log for Tenant B
        $userB = UserModel::factory()->user()->forTenant($tenantB)->create();

        // Fetch activity logs scoped to Tenant A
        $responseA = $this->getJson('/api/v1/activity-logs', $this->tenantHeaders($tenantA, $tokenA));
        $responseA->assertStatus(200);

        $logSubjectIds = array_column($responseA->json('data'), 'subject_id');
        $this->assertNotContains($userB->id, $logSubjectIds);
    }

    public function test_tenants_share_no_activity_log_data(): void
    {
        ['tenant' => $tenantA, 'admin' => $adminA, 'token' => $tokenA] = $this->createTenantWithAdmin();
        ['tenant' => $tenantB, 'admin' => $adminB, 'token' => $tokenB] = $this->createTenantWithAdmin();

        // Tenant A admin creates a user → generates a log in Tenant A
        $this->postJson('/api/v1/users', [
            'name' => 'Tenant A User',
            'email' => 'ta-user@example.com',
            'password' => 'password123',
        ], $this->tenantHeaders($tenantA, $tokenA));

        // Tenant B admin should see zero logs
        $responseB = $this->getJson('/api/v1/activity-logs', $this->tenantHeaders($tenantB, $tokenB));
        $responseB->assertStatus(200);

        // Only Tenant B's own logs should appear (admin creation)
        $tenantAUserSubjectIds = array_filter(
            array_column($responseB->json('data'), 'subject_id'),
            fn ($id) => $id === null
        );

        foreach ($responseB->json('data') as $log) {
            $this->assertNotSame('ta-user@example.com', $log['properties']['new_values']['email'] ?? null);
        }
    }

    // ── Super admin scoping ──

    public function test_super_admin_with_tenant_header_sees_only_that_tenant(): void
    {
        ['tenant' => $tenantA] = $this->createTenantWithAdmin();
        ['tenant' => $tenantB] = $this->createTenantWithAdmin();
        $userA = UserModel::factory()->user()->forTenant($tenantA)->create();
        $userB = UserModel::factory()->user()->forTenant($tenantB)->create();

        ['superAdmin' => $superAdmin, 'token' => $token] = $this->createSuperAdmin();

        $response = $this->getJson('/api/v1/users', $this->tenantHeaders($tenantA, $token));

        $response->assertStatus(200);
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($userA->id, $ids);
        $this->assertNotContains($userB->id, $ids);
    }

    public function test_super_admin_without_tenant_header_sees_all_tenants_users(): void
    {
        ['tenant' => $tenantA] = $this->createTenantWithAdmin();
        ['tenant' => $tenantB] = $this->createTenantWithAdmin();
        $userA = UserModel::factory()->user()->forTenant($tenantA)->create();
        $userB = UserModel::factory()->user()->forTenant($tenantB)->create();

        ['token' => $token] = $this->createSuperAdmin();

        $response = $this->getJson('/api/v1/users', ['Authorization' => 'Bearer '.$token]);

        $response->assertStatus(200);
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($userA->id, $ids);
        $this->assertContains($userB->id, $ids);
    }
}
