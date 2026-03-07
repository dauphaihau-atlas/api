<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    // ── Soft Delete ──

    public function test_admin_can_soft_delete_user(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        $user = UserModel::factory()->forTenant($tenant)->create();

        $response = $this->deleteJson('/api/v1/users/'.$user->id, [], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'User deleted successfully');
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_non_admin_cannot_delete_user(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'token' => $token] = $this->createTenantWithUser();
        $target = UserModel::factory()->forTenant($tenant)->create();

        $response = $this->deleteJson('/api/v1/users/'.$target->id, [], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(403);
    }

    public function test_delete_nonexistent_user_returns_404(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->deleteJson('/api/v1/users/99999', [], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(404);
    }

    // ── Listing with trashed filter ──

    public function test_soft_deleted_users_hidden_by_default(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        $user = UserModel::factory()->forTenant($tenant)->create();
        $user->delete();

        $response = $this->getJson('/api/v1/users', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $ids = array_column($response->json('data'), 'id');
        $this->assertNotContains($user->id, $ids);
    }

    public function test_trashed_only_shows_soft_deleted_users(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        $active = UserModel::factory()->forTenant($tenant)->create();
        $deleted = UserModel::factory()->forTenant($tenant)->create();
        $deleted->delete();

        $response = $this->getJson('/api/v1/users?trashed=only', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($deleted->id, $response->json('data.0.id'));
        $this->assertNotNull($response->json('data.0.deleted_at'));
    }

    public function test_trashed_with_shows_all_users(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        $active = UserModel::factory()->forTenant($tenant)->create();
        $deleted = UserModel::factory()->forTenant($tenant)->create();
        $deleted->delete();

        $response = $this->getJson('/api/v1/users?trashed=with', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        // admin + active + deleted = 3
        $this->assertSame(3, $response->json('meta.total'));
    }

    public function test_invalid_trashed_filter_returns_400(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->getJson('/api/v1/users?trashed=invalid', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(422);
    }

    // ── Restore ──

    public function test_admin_can_restore_soft_deleted_user(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        $user = UserModel::factory()->forTenant($tenant)->create();
        $user->delete();

        $response = $this->postJson('/api/v1/users/'.$user->id.'/restore', [], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'User restored successfully');
        $response->assertJsonPath('data.id', $user->id);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'deleted_at' => null,
        ]);
    }

    public function test_non_admin_cannot_restore_user(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'token' => $token] = $this->createTenantWithUser();
        $target = UserModel::factory()->forTenant($tenant)->create();
        $target->delete();

        $response = $this->postJson('/api/v1/users/'.$target->id.'/restore', [], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(403);
    }

    public function test_restore_nonexistent_trashed_user_returns_404(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->postJson('/api/v1/users/99999/restore', [], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(404);
    }

    // ── Force Delete ──

    public function test_admin_can_force_delete_user(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        $user = UserModel::factory()->forTenant($tenant)->create();
        $userId = $user->id;

        $response = $this->deleteJson('/api/v1/users/'.$userId.'/force', [], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'User permanently deleted');
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_admin_can_force_delete_soft_deleted_user(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        $user = UserModel::factory()->forTenant($tenant)->create();
        $userId = $user->id;
        $user->delete();

        $response = $this->deleteJson('/api/v1/users/'.$userId.'/force', [], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_non_admin_cannot_force_delete_user(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'token' => $token] = $this->createTenantWithUser();
        $target = UserModel::factory()->forTenant($tenant)->create();

        $response = $this->deleteJson('/api/v1/users/'.$target->id.'/force', [], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(403);
    }

    public function test_force_delete_nonexistent_user_returns_404(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->deleteJson('/api/v1/users/99999/force', [], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(404);
    }

    // ── Activity Log ──

    public function test_soft_delete_creates_activity_log(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        $user = UserModel::factory()->forTenant($tenant)->create();

        $this->deleteJson('/api/v1/users/'.$user->id, [], $this->tenantHeaders($tenant, $token));

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'deleted',
            'subject_id' => $user->id,
            'causer_id' => $admin->id,
        ]);
    }

    public function test_restore_creates_activity_log(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        $user = UserModel::factory()->forTenant($tenant)->create();
        $user->delete();

        $this->postJson('/api/v1/users/'.$user->id.'/restore', [], $this->tenantHeaders($tenant, $token));

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'restored',
            'subject_id' => $user->id,
            'causer_id' => $admin->id,
        ]);
    }

    public function test_force_delete_creates_activity_log(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        $user = UserModel::factory()->forTenant($tenant)->create();
        $userId = $user->id;

        $this->deleteJson('/api/v1/users/'.$userId.'/force', [], $this->tenantHeaders($tenant, $token));

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'force_deleted',
            'subject_id' => $userId,
            'causer_id' => $admin->id,
        ]);
    }
}
