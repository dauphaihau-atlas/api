<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateProfileApiTest extends TestCase
{
    use RefreshDatabase;

    // ── Happy paths ──

    public function test_user_can_update_own_name(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'token' => $token] = $this->createTenantWithUser();

        $response = $this->patchJson('/api/v1/me', [
            'version' => $user->version,
            'name' => 'Updated Name',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Updated Name');
        $response->assertJsonPath('data.version', 2);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Name', 'version' => 2]);
    }

    public function test_user_can_update_own_email(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'token' => $token] = $this->createTenantWithUser();

        $response = $this->patchJson('/api/v1/me', [
            'version' => $user->version,
            'email' => 'mynewemail@example.com',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $response->assertJsonPath('data.email', 'mynewemail@example.com');
    }

    public function test_user_can_update_own_password(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'token' => $token] = $this->createTenantWithUser();

        $response = $this->patchJson('/api/v1/me', [
            'version' => $user->version,
            'password' => 'newpassword123',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $response->assertJsonPath('data.version', 2);
    }

    public function test_admin_can_update_own_profile(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->patchJson('/api/v1/me', [
            'version' => $admin->version,
            'name' => 'Admin Updated',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Admin Updated');
    }

    public function test_response_includes_version_field(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'token' => $token] = $this->createTenantWithUser();

        $response = $this->patchJson('/api/v1/me', [
            'version' => $user->version,
            'name' => 'Updated',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['id', 'name', 'email', 'version', 'updated_at']]);
    }

    // ── Conflict (409) ──

    public function test_stale_version_returns_409(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithUser();

        $response = $this->patchJson('/api/v1/me', [
            'version' => 999,
            'name' => 'Stale',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(409);
    }

    public function test_concurrent_update_second_request_returns_409(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'token' => $token] = $this->createTenantWithUser();
        $originalVersion = $user->version;

        // First update succeeds
        $this->patchJson('/api/v1/me', [
            'version' => $originalVersion,
            'name' => 'First Update',
        ], $this->tenantHeaders($tenant, $token))->assertStatus(200);

        // Second update with same original version fails
        $response = $this->patchJson('/api/v1/me', [
            'version' => $originalVersion,
            'name' => 'Second Update',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(409);
    }

    public function test_duplicate_email_returns_409(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'token' => $token] = $this->createTenantWithUser();
        UserModel::factory()->user()->forTenant($tenant)->create(['email' => 'taken@example.com']);

        $response = $this->patchJson('/api/v1/me', [
            'version' => $user->version,
            'email' => 'taken@example.com',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(409);
    }

    // ── Validation (422) ──

    public function test_missing_version_returns_422(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithUser();

        $response = $this->patchJson('/api/v1/me', [
            'name' => 'No Version',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['version']);
    }

    public function test_invalid_email_format_returns_422(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'token' => $token] = $this->createTenantWithUser();

        $response = $this->patchJson('/api/v1/me', [
            'version' => $user->version,
            'email' => 'not-an-email',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    // ── Authentication ──

    public function test_unauthenticated_request_returns_401(): void
    {
        ['tenant' => $tenant] = $this->createTenantWithUser();

        $response = $this->patchJson('/api/v1/me', [
            'version' => 1,
            'name' => 'No Auth',
        ], ['Accept' => 'application/json', 'X-Tenant-ID' => $tenant->slug]);

        $response->assertStatus(401);
    }
}
