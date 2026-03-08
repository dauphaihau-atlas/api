<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTenantTest extends TestCase
{
    use RefreshDatabase;

    // ── Tenant user login ──

    public function test_tenant_user_can_login_with_correct_tenant_header(): void
    {
        ['tenant' => $tenant] = $this->createTenantWithAdmin();
        $user = UserModel::factory()
            ->user()
            ->forTenant($tenant)
            ->create(['password' => bcrypt('secret')]);

        $response = $this->postJson(
            '/api/v1/login',
            [
                'email' => $user->email->getValue(),
                'password' => 'secret',
            ],
            [
                'Accept' => 'application/json',
                'X-Tenant-ID' => $tenant->slug,
            ],
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_tenant_user_cannot_login_with_wrong_tenant_header(): void
    {
        ['tenant' => $tenantA] = $this->createTenantWithAdmin();
        ['tenant' => $tenantB] = $this->createTenantWithAdmin();
        $userA = UserModel::factory()
            ->user()
            ->forTenant($tenantA)
            ->create(['password' => bcrypt('secret')]);

        // Alice belongs to Tenant A but tries to log in with Tenant B's header
        $response = $this->postJson(
            '/api/v1/login',
            [
                'email' => $userA->email->getValue(),
                'password' => 'secret',
            ],
            [
                'Accept' => 'application/json',
                'X-Tenant-ID' => $tenantB->slug,
            ],
        );

        $response->assertStatus(401);
    }

    public function test_tenant_user_can_login_without_tenant_header(): void
    {
        ['tenant' => $tenant] = $this->createTenantWithAdmin();
        $user = UserModel::factory()
            ->user()
            ->forTenant($tenant)
            ->create(['password' => bcrypt('secret')]);

        // Login without header — optional middleware skips check
        $response = $this->postJson(
            '/api/v1/login',
            [
                'email' => $user->email->getValue(),
                'password' => 'secret',
            ],
            ['Accept' => 'application/json'],
        );

        $response->assertStatus(200);
    }

    // ── Super admin login ──

    public function test_super_admin_can_login_without_tenant_header(): void
    {
        $superAdmin = UserModel::factory()
            ->superAdmin()
            ->create(['password' => bcrypt('secret')]);

        $response = $this->postJson(
            '/api/v1/login',
            [
                'email' => $superAdmin->email->getValue(),
                'password' => 'secret',
            ],
            ['Accept' => 'application/json'],
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_super_admin_cannot_login_with_tenant_header(): void
    {
        ['tenant' => $tenant] = $this->createTenantWithAdmin();
        $superAdmin = UserModel::factory()
            ->superAdmin()
            ->create(['password' => bcrypt('secret')]);

        // Super admin (tenantId = null) does not belong to any tenant → 401
        $response = $this->postJson(
            '/api/v1/login',
            [
                'email' => $superAdmin->email->getValue(),
                'password' => 'secret',
            ],
            [
                'Accept' => 'application/json',
                'X-Tenant-ID' => $tenant->slug,
            ],
        );

        $response->assertStatus(401);
    }

    // ── Invalid credentials ──

    public function test_wrong_password_returns_401(): void
    {
        ['tenant' => $tenant] = $this->createTenantWithAdmin();
        $user = UserModel::factory()
            ->user()
            ->forTenant($tenant)
            ->create(['password' => bcrypt('correct')]);

        $response = $this->postJson(
            '/api/v1/login',
            [
                'email' => $user->email->getValue(),
                'password' => 'wrong-password',
            ],
            [
                'Accept' => 'application/json',
                'X-Tenant-ID' => $tenant->slug,
            ],
        );

        $response->assertStatus(401);
    }
}
