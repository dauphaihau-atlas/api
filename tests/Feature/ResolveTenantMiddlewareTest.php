<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\TenantModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveTenantMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    // ── Required middleware (resolve.tenant) ──

    public function test_missing_header_returns_422(): void
    {
        ['admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->getJson('/api/v1/users', [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
            // No X-Tenant-ID header
        ]);

        $response->assertStatus(422);
    }

    public function test_unknown_slug_returns_404(): void
    {
        ['token' => $token] = $this->createTenantWithAdmin();

        $response = $this->getJson('/api/v1/users', [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
            'X-Tenant-ID' => 'non-existent-slug',
        ]);

        $response->assertStatus(404);
    }

    public function test_inactive_tenant_returns_422(): void
    {
        $tenant = TenantModel::factory()->inactive()->create();
        $admin = UserModel::factory()->admin()->forTenant($tenant)->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/users', [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
            'X-Tenant-ID' => $tenant->slug,
        ]);

        $response->assertStatus(422);
    }

    public function test_valid_tenant_header_allows_request(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->getJson('/api/v1/users', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
    }

    public function test_unauthenticated_request_returns_401_before_tenant_check(): void
    {
        $tenant = TenantModel::factory()->create();

        $response = $this->getJson('/api/v1/users', [
            'Accept' => 'application/json',
            'X-Tenant-ID' => $tenant->slug,
        ]);

        $response->assertStatus(401);
    }

    // ── Optional middleware (resolve.tenant.optional) on login ──

    public function test_login_succeeds_without_tenant_header(): void
    {
        $user = UserModel::factory()->user()->create(['password' => bcrypt('secret')]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'secret',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['token']]);
    }

    public function test_login_with_valid_active_tenant_header_succeeds(): void
    {
        ['tenant' => $tenant] = $this->createTenantWithAdmin();
        $user = UserModel::factory()->user()->forTenant($tenant)->create(['password' => bcrypt('secret')]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'secret',
        ], [
            'Accept' => 'application/json',
            'X-Tenant-ID' => $tenant->slug,
        ]);

        $response->assertStatus(200);
    }

    public function test_login_with_unknown_optional_tenant_header_returns_404(): void
    {
        $user = UserModel::factory()->user()->create(['password' => bcrypt('secret')]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'secret',
        ], [
            'Accept' => 'application/json',
            'X-Tenant-ID' => 'non-existent-slug',
        ]);

        $response->assertStatus(404);
    }

    public function test_login_with_inactive_optional_tenant_header_returns_422(): void
    {
        $tenant = TenantModel::factory()->inactive()->create();
        $user = UserModel::factory()->user()->forTenant($tenant)->create(['password' => bcrypt('secret')]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'secret',
        ], [
            'Accept' => 'application/json',
            'X-Tenant-ID' => $tenant->slug,
        ]);

        $response->assertStatus(422);
    }
}
