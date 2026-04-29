<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\TenantModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignableRolesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_fetch_non_elevated_assignable_roles(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->getJson('/api/v1/roles/assignable', $this->tenantHeaders($tenant, $token));

        $response->assertOk();
        $response->assertJsonPath('data.0.slug', 'user');
        $response->assertJsonPath('data.1.slug', 'viewer');
        $response->assertJsonPath('data.2.slug', 'support');
        $this->assertSame(['user', 'viewer', 'support'], array_column($response->json('data'), 'slug'));
    }

    public function test_support_can_fetch_non_elevated_assignable_roles(): void
    {
        $tenant = TenantModel::factory()->create();
        $support = UserModel::factory()->support()->forTenant($tenant)->create();
        $token = $support->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/roles/assignable', $this->tenantHeaders($tenant, $token));

        $response->assertOk();
        $this->assertSame(['user', 'viewer', 'support'], array_column($response->json('data'), 'slug'));
    }

    public function test_tenant_owner_can_fetch_elevated_assignable_roles(): void
    {
        $tenant = TenantModel::factory()->create();
        $owner = UserModel::factory()->tenantOwner()->forTenant($tenant)->create();
        $token = $owner->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/roles/assignable', $this->tenantHeaders($tenant, $token));

        $response->assertOk();
        $this->assertSame(['user', 'viewer', 'support', 'admin', 'tenant_owner'], array_column($response->json('data'), 'slug'));
    }

    public function test_viewer_cannot_fetch_assignable_roles(): void
    {
        $tenant = TenantModel::factory()->create();
        $viewer = UserModel::factory()->viewer()->forTenant($tenant)->create();
        $token = $viewer->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/roles/assignable', $this->tenantHeaders($tenant, $token));

        $response->assertForbidden();
    }
}
