<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateUserApiTest extends TestCase
{
    use RefreshDatabase;

    // ── Happy paths ──

    public function test_admin_can_update_user_name(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();
        $user = UserModel::factory()->user()->forTenant($tenant)->create(['name' => 'Old Name']);

        $response = $this->patchJson('/api/v1/users/'.$user->id, [
            'version' => $user->version,
            'name' => 'New Name',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'New Name');
        $response->assertJsonPath('data.version', 2);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name', 'version' => 2]);
    }

    public function test_admin_can_update_user_email(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();
        $user = UserModel::factory()->user()->forTenant($tenant)->create();

        $response = $this->patchJson('/api/v1/users/'.$user->id, [
            'version' => $user->version,
            'email' => 'newemail@example.com',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $response->assertJsonPath('data.email', 'newemail@example.com');
        $response->assertJsonPath('data.version', 2);
    }

    public function test_admin_can_update_user_password(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();
        $user = UserModel::factory()->user()->forTenant($tenant)->create();

        $response = $this->patchJson('/api/v1/users/'.$user->id, [
            'version' => $user->version,
            'password' => 'newpassword123',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $response->assertJsonPath('data.version', 2);
    }

    public function test_response_includes_version_field(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();
        $user = UserModel::factory()->user()->forTenant($tenant)->create();

        $response = $this->patchJson('/api/v1/users/'.$user->id, [
            'version' => $user->version,
            'name' => 'Updated',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['id', 'name', 'email', 'version', 'updated_at']]);
    }

    // ── Conflict (409) ──

    public function test_stale_version_returns_409(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();
        $user = UserModel::factory()->user()->forTenant($tenant)->create();

        $response = $this->patchJson('/api/v1/users/'.$user->id, [
            'version' => 999,
            'name' => 'Stale Update',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(409);
    }

    public function test_concurrent_update_second_request_returns_409(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();
        $user = UserModel::factory()->user()->forTenant($tenant)->create();
        $originalVersion = $user->version;

        // First update succeeds
        $this->patchJson('/api/v1/users/'.$user->id, [
            'version' => $originalVersion,
            'name' => 'First Update',
        ], $this->tenantHeaders($tenant, $token))->assertStatus(200);

        // Second update with same version fails
        $response = $this->patchJson('/api/v1/users/'.$user->id, [
            'version' => $originalVersion,
            'name' => 'Second Update',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(409);
    }

    public function test_duplicate_email_returns_409(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();
        UserModel::factory()->user()->forTenant($tenant)->create(['email' => 'taken@example.com']);
        $user = UserModel::factory()->user()->forTenant($tenant)->create();

        $response = $this->patchJson('/api/v1/users/'.$user->id, [
            'version' => $user->version,
            'email' => 'taken@example.com',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(409);
    }

    // ── Validation (422) ──

    public function test_missing_version_returns_422(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();
        $user = UserModel::factory()->user()->forTenant($tenant)->create();

        $response = $this->patchJson('/api/v1/users/'.$user->id, [
            'name' => 'No Version',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['version']);
    }

    public function test_invalid_email_format_returns_422(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();
        $user = UserModel::factory()->user()->forTenant($tenant)->create();

        $response = $this->patchJson('/api/v1/users/'.$user->id, [
            'version' => $user->version,
            'email' => 'not-an-email',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    // ── Authorization ──

    public function test_non_admin_cannot_update_user(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithUser();
        $user = UserModel::factory()->user()->forTenant($tenant)->create();

        $response = $this->patchJson('/api/v1/users/'.$user->id, [
            'version' => $user->version,
            'name' => 'Hacked',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(403);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        ['tenant' => $tenant] = $this->createTenantWithAdmin();
        $user = UserModel::factory()->user()->forTenant($tenant)->create();

        $response = $this->patchJson('/api/v1/users/'.$user->id, [
            'version' => $user->version,
            'name' => 'No Auth',
        ], ['Accept' => 'application/json', 'X-Tenant-ID' => $tenant->slug]);

        $response->assertStatus(401);
    }

    // ── Not found ──

    public function test_update_nonexistent_user_returns_404(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->patchJson('/api/v1/users/99999', [
            'version' => 1,
            'name' => 'Ghost',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(404);
    }
}
