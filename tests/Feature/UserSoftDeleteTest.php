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
        $admin = UserModel::factory()->admin()->create();
        $user = UserModel::factory()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->deleteJson('/api/v1/users/'.$user->id, [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'User deleted successfully');
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_non_admin_cannot_delete_user(): void
    {
        $user = UserModel::factory()->create();
        $target = UserModel::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->deleteJson('/api/v1/users/'.$target->id, [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(403);
    }

    public function test_delete_nonexistent_user_returns_404(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->deleteJson('/api/v1/users/99999', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(404);
    }

    // ── Listing with trashed filter ──

    public function test_soft_deleted_users_hidden_by_default(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $user = UserModel::factory()->create();
        $user->delete();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/users', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $ids = array_column($response->json('data'), 'id');
        $this->assertNotContains($user->id, $ids);
    }

    public function test_trashed_only_shows_soft_deleted_users(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $active = UserModel::factory()->create();
        $deleted = UserModel::factory()->create();
        $deleted->delete();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/users?trashed=only', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($deleted->id, $response->json('data.0.id'));
        $this->assertNotNull($response->json('data.0.deleted_at'));
    }

    public function test_trashed_with_shows_all_users(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $active = UserModel::factory()->create();
        $deleted = UserModel::factory()->create();
        $deleted->delete();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/users?trashed=with', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        // admin + active + deleted = 3
        $this->assertSame(3, $response->json('meta.total'));
    }

    public function test_invalid_trashed_filter_returns_400(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/users?trashed=invalid', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(422);
    }

    // ── Restore ──

    public function test_admin_can_restore_soft_deleted_user(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $user = UserModel::factory()->create();
        $user->delete();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->postJson('/api/v1/users/'.$user->id.'/restore', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

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
        $user = UserModel::factory()->create();
        $target = UserModel::factory()->create();
        $target->delete();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->postJson('/api/v1/users/'.$target->id.'/restore', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(403);
    }

    public function test_restore_nonexistent_trashed_user_returns_404(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->postJson('/api/v1/users/99999/restore', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(404);
    }

    // ── Force Delete ──

    public function test_admin_can_force_delete_user(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $user = UserModel::factory()->create();
        $userId = $user->id;
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->deleteJson('/api/v1/users/'.$userId.'/force', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'User permanently deleted');
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_admin_can_force_delete_soft_deleted_user(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $user = UserModel::factory()->create();
        $userId = $user->id;
        $user->delete();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->deleteJson('/api/v1/users/'.$userId.'/force', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_non_admin_cannot_force_delete_user(): void
    {
        $user = UserModel::factory()->create();
        $target = UserModel::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->deleteJson('/api/v1/users/'.$target->id.'/force', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(403);
    }

    public function test_force_delete_nonexistent_user_returns_404(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->deleteJson('/api/v1/users/99999/force', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(404);
    }

    // ── Activity Log ──

    public function test_soft_delete_creates_activity_log(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $user = UserModel::factory()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $this->deleteJson('/api/v1/users/'.$user->id, [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'event' => 'deleted',
            'subject_id' => $user->id,
            'causer_id' => $admin->id,
        ]);
    }

    public function test_restore_creates_activity_log(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $user = UserModel::factory()->create();
        $user->delete();
        $token = $admin->createToken('test')->plainTextToken;

        $this->postJson('/api/v1/users/'.$user->id.'/restore', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'event' => 'restored',
            'subject_id' => $user->id,
            'causer_id' => $admin->id,
        ]);
    }

    public function test_force_delete_creates_activity_log(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $user = UserModel::factory()->create();
        $userId = $user->id;
        $token = $admin->createToken('test')->plainTextToken;

        $this->deleteJson('/api/v1/users/'.$userId.'/force', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'event' => 'force_deleted',
            'subject_id' => $userId,
            'causer_id' => $admin->id,
        ]);
    }
}
