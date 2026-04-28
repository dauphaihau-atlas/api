<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\TenantModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantApiTest extends TestCase
{
    use RefreshDatabase;

    // ── List (viewAny) ──

    public function test_super_admin_can_list_tenants(): void
    {
        ['token' => $token] = $this->createSuperAdmin();
        TenantModel::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/tenants', [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta']);
        $this->assertGreaterThanOrEqual(3, $response->json('meta.total'));
    }

    public function test_regular_admin_cannot_list_tenants(): void
    {
        ['token' => $token] = $this->createTenantWithAdmin();

        $response = $this->getJson('/api/v1/tenants', [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_list_tenants(): void
    {
        $response = $this->getJson('/api/v1/tenants', ['Accept' => 'application/json']);

        $response->assertStatus(401);
    }

    // ── Show ──

    public function test_super_admin_can_view_tenant(): void
    {
        ['token' => $token] = $this->createSuperAdmin();
        $tenant = TenantModel::factory()->create();

        $response = $this->getJson('/api/v1/tenants/'.$tenant->id, [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $tenant->id);
        $response->assertJsonPath('data.slug', $tenant->slug);
    }

    public function test_show_returns_404_for_nonexistent_tenant(): void
    {
        ['token' => $token] = $this->createSuperAdmin();

        $response = $this->getJson('/api/v1/tenants/99999', [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(404);
    }

    // ── Create ──

    public function test_super_admin_can_create_tenant(): void
    {
        ['token' => $token] = $this->createSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'is_active' => true,
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.slug', 'acme-corp');
        $this->assertDatabaseHas('tenants', ['slug' => 'acme-corp']);
    }

    public function test_create_tenant_returns_422_for_duplicate_slug(): void
    {
        ['token' => $token] = $this->createSuperAdmin();
        TenantModel::factory()->create(['slug' => 'existing-slug']);

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Another Corp',
            'slug' => 'existing-slug',
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['slug']);
    }

    public function test_create_tenant_returns_422_for_missing_fields(): void
    {
        ['token' => $token] = $this->createSuperAdmin();

        $response = $this->postJson('/api/v1/tenants', [], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);
    }

    public function test_regular_admin_cannot_create_tenant(): void
    {
        ['token' => $token] = $this->createTenantWithAdmin();

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Corp',
            'slug' => 'corp',
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(403);
    }

    // ── Update ──

    public function test_super_admin_can_update_tenant(): void
    {
        ['token' => $token] = $this->createSuperAdmin();
        $tenant = TenantModel::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson('/api/v1/tenants/'.$tenant->id, [
            'version' => $tenant->version,
            'name' => 'New Name',
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'New Name');
        $response->assertJsonPath('data.version', 2);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'name' => 'New Name', 'version' => 2]);
    }

    public function test_update_tenant_returns_404_for_nonexistent(): void
    {
        ['token' => $token] = $this->createSuperAdmin();

        $response = $this->putJson('/api/v1/tenants/99999', [
            'version' => 1,
            'name' => 'New Name',
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(404);
    }

    public function test_update_without_settings_field_does_not_clear_existing_settings(): void
    {
        ['token' => $token] = $this->createSuperAdmin();
        $tenant = TenantModel::factory()->create(['settings' => ['theme' => 'dark']]);

        $response = $this->putJson('/api/v1/tenants/'.$tenant->id, [
            'version' => $tenant->version,
            'name' => 'Updated Name',
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'name' => 'Updated Name']);
        $this->assertEquals(['theme' => 'dark'], TenantModel::find($tenant->id)->settings);
    }

    public function test_update_with_null_settings_clears_existing_settings(): void
    {
        ['token' => $token] = $this->createSuperAdmin();
        $tenant = TenantModel::factory()->create(['settings' => ['theme' => 'dark']]);

        $response = $this->putJson('/api/v1/tenants/'.$tenant->id, [
            'version' => $tenant->version,
            'settings' => null,
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $this->assertNull(TenantModel::find($tenant->id)->settings);
    }

    public function test_update_returns_422_for_duplicate_slug(): void
    {
        ['token' => $token] = $this->createSuperAdmin();
        TenantModel::factory()->create(['slug' => 'taken-slug']);
        $tenant = TenantModel::factory()->create(['slug' => 'my-slug']);

        $response = $this->putJson('/api/v1/tenants/'.$tenant->id, [
            'version' => $tenant->version,
            'slug' => 'taken-slug',
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['slug']);
    }

    public function test_update_missing_version_returns_422(): void
    {
        ['token' => $token] = $this->createSuperAdmin();
        $tenant = TenantModel::factory()->create();

        $response = $this->putJson('/api/v1/tenants/'.$tenant->id, [
            'name' => 'No Version',
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['version']);
    }

    public function test_update_stale_version_returns_409(): void
    {
        ['token' => $token] = $this->createSuperAdmin();
        $tenant = TenantModel::factory()->create();

        $response = $this->putJson('/api/v1/tenants/'.$tenant->id, [
            'version' => 999,
            'name' => 'Stale',
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(409);
    }

    public function test_regular_admin_cannot_update_tenant(): void
    {
        ['token' => $token] = $this->createTenantWithAdmin();
        $tenant = TenantModel::factory()->create();

        $response = $this->putJson('/api/v1/tenants/'.$tenant->id, [
            'name' => 'Hacked',
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(403);
    }

    // ── Delete ──

    public function test_super_admin_can_delete_tenant(): void
    {
        ['token' => $token] = $this->createSuperAdmin();
        $tenant = TenantModel::factory()->create();

        $response = $this->deleteJson('/api/v1/tenants/'.$tenant->id, [], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
    }

    public function test_delete_returns_404_for_nonexistent_tenant(): void
    {
        ['token' => $token] = $this->createSuperAdmin();

        $response = $this->deleteJson('/api/v1/tenants/99999', [], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(404);
    }

    public function test_regular_admin_cannot_delete_tenant(): void
    {
        ['token' => $token] = $this->createTenantWithAdmin();
        $tenant = TenantModel::factory()->create();

        $response = $this->deleteJson('/api/v1/tenants/'.$tenant->id, [], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(403);
    }
}
