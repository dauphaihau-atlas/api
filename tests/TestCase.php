<?php

namespace Tests;

use App\Infrastructure\Persistence\Eloquent\Models\TenantModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create a tenant with an admin user and return an array with tenant, admin, and token.
     *
     * @return array{tenant: TenantModel, admin: UserModel, token: string}
     */
    protected function createTenantWithAdmin(): array
    {
        $tenant = TenantModel::factory()->create();
        $admin = UserModel::factory()->admin()->forTenant($tenant)->create();
        $token = $admin->createToken('test')->plainTextToken;

        return compact('tenant', 'admin', 'token');
    }

    /**
     * Create a tenant with a regular (non-admin) user and return an array with tenant, user, and token.
     *
     * @return array{tenant: TenantModel, user: UserModel, token: string}
     */
    protected function createTenantWithUser(): array
    {
        $tenant = TenantModel::factory()->create();
        $user = UserModel::factory()->user()->forTenant($tenant)->create();
        $token = $user->createToken('test')->plainTextToken;

        return compact('tenant', 'user', 'token');
    }

    /**
     * Create a super admin user (no tenant) and return the model and a plain-text token.
     *
     * @return array{superAdmin: UserModel, token: string}
     */
    protected function createSuperAdmin(): array
    {
        $superAdmin = UserModel::factory()->superAdmin()->create();
        $token = $superAdmin->createToken('test')->plainTextToken;

        return ['superAdmin' => $superAdmin, 'token' => $token];
    }

    /**
     * Build default headers for tenant-scoped API requests.
     *
     * @return array<string, string>
     */
    protected function tenantHeaders(TenantModel $tenant, string $token): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
            'X-Tenant-ID' => $tenant->slug,
        ];
    }
}
