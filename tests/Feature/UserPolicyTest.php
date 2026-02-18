<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Presentation\Http\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    private UserPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new UserPolicy;
    }

    // ── viewAny ──

    public function test_view_any_allowed_for_admin(): void
    {
        $admin = UserModel::factory()->admin()->create();

        $this->assertTrue($this->policy->viewAny($admin));
    }

    public function test_view_any_denied_for_regular_user(): void
    {
        $user = UserModel::factory()->create(['role' => 'user']);

        $this->assertFalse($this->policy->viewAny($user));
    }

    // ── view ──

    public function test_view_allowed_for_admin(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $target = UserModel::factory()->create(['role' => 'user']);

        $this->assertTrue($this->policy->view($admin, $target));
    }

    public function test_view_allowed_for_owner(): void
    {
        $user = UserModel::factory()->create(['role' => 'user']);

        $this->assertTrue($this->policy->view($user, $user));
    }

    public function test_view_denied_for_non_owner(): void
    {
        $user = UserModel::factory()->create(['role' => 'user']);
        $other = UserModel::factory()->create(['role' => 'user']);

        $this->assertFalse($this->policy->view($user, $other));
    }

    // ── create ──

    public function test_create_allowed_for_admin(): void
    {
        $admin = UserModel::factory()->admin()->create();

        $this->assertTrue($this->policy->create($admin));
    }

    public function test_create_denied_for_regular_user(): void
    {
        $user = UserModel::factory()->create(['role' => 'user']);

        $this->assertFalse($this->policy->create($user));
    }

    // ── update ──

    public function test_update_allowed_for_admin(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $target = UserModel::factory()->create(['role' => 'user']);

        $this->assertTrue($this->policy->update($admin, $target));
    }

    public function test_update_allowed_for_owner(): void
    {
        $user = UserModel::factory()->create(['role' => 'user']);

        $this->assertTrue($this->policy->update($user, $user));
    }

    public function test_update_denied_for_non_owner(): void
    {
        $user = UserModel::factory()->create(['role' => 'user']);
        $other = UserModel::factory()->create(['role' => 'user']);

        $this->assertFalse($this->policy->update($user, $other));
    }

    // ── delete ──

    public function test_delete_allowed_for_admin(): void
    {
        $admin = UserModel::factory()->admin()->create();

        $this->assertTrue($this->policy->delete($admin));
    }

    public function test_delete_denied_for_regular_user(): void
    {
        $user = UserModel::factory()->create(['role' => 'user']);

        $this->assertFalse($this->policy->delete($user));
    }

    // ── import ──

    public function test_import_allowed_for_admin(): void
    {
        $admin = UserModel::factory()->admin()->create();

        $this->assertTrue($this->policy->import($admin));
    }

    public function test_import_denied_for_regular_user(): void
    {
        $user = UserModel::factory()->create(['role' => 'user']);

        $this->assertFalse($this->policy->import($user));
    }

    // ── export ──

    public function test_export_allowed_for_admin(): void
    {
        $admin = UserModel::factory()->admin()->create();

        $this->assertTrue($this->policy->export($admin));
    }

    public function test_export_denied_for_regular_user(): void
    {
        $user = UserModel::factory()->create(['role' => 'user']);

        $this->assertFalse($this->policy->export($user));
    }

    // ── restore ──

    public function test_restore_allowed_for_admin(): void
    {
        $admin = UserModel::factory()->admin()->create();

        $this->assertTrue($this->policy->restore($admin));
    }

    public function test_restore_denied_for_regular_user(): void
    {
        $user = UserModel::factory()->create(['role' => 'user']);

        $this->assertFalse($this->policy->restore($user));
    }

    // ── forceDelete ──

    public function test_force_delete_allowed_for_admin(): void
    {
        $admin = UserModel::factory()->admin()->create();

        $this->assertTrue($this->policy->forceDelete($admin));
    }

    public function test_force_delete_denied_for_regular_user(): void
    {
        $user = UserModel::factory()->create(['role' => 'user']);

        $this->assertFalse($this->policy->forceDelete($user));
    }

    // ── Integration: routes enforce policy ──

    public function test_users_index_returns_403_for_non_admin(): void
    {
        $user = UserModel::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/users', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(403);
    }

    public function test_users_index_returns_200_for_admin(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/users', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
    }

    public function test_avatar_update_allowed_for_owner(): void
    {
        $user = UserModel::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->postJson('/api/v1/users/'.$user->id.'/avatar', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        // 422 means authorization passed but validation failed (no file)
        $response->assertStatus(422);
    }

    public function test_avatar_update_denied_for_non_owner(): void
    {
        $user = UserModel::factory()->create(['role' => 'user']);
        $other = UserModel::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->postJson('/api/v1/users/'.$other->id.'/avatar', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(403);
    }
}
