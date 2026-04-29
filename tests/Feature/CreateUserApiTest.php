<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\UserCreated;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\UserCreatedNotification;
use App\Notifications\UserInviteNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CreateUserApiTest extends TestCase
{
    use RefreshDatabase;

    // ── Happy path ──

    public function test_admin_can_create_user(): void
    {
        Event::fake([UserCreated::class]);

        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret123',
            'role' => 'user',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(201);
        $response->assertJsonStructure(['data' => ['id', 'name', 'email', 'roles', 'invitation_status', 'created_at']]);
        $response->assertJsonPath('data.roles.0', 'user');
        $response->assertJsonPath('data.invitation_status', 'not_sent');
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $created = UserModel::where('email', 'jane@example.com')->firstOrFail();
        $this->assertTrue($created->roles()->where('slug', 'user')->exists());
        Event::assertDispatched(UserCreated::class, fn (UserCreated $e) => $e->user->getEmail()->getValue() === 'jane@example.com');
    }

    public function test_admin_can_invite_user_without_setting_password(): void
    {
        Event::fake([UserCreated::class]);
        Notification::fake();

        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Invited Admin',
            'email' => 'invited@example.com',
            'role' => 'admin',
            'send_invite' => true,
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(201);
        $response->assertJsonPath('data.roles.0', 'admin');
        $response->assertJsonPath('data.invitation_status', 'sent');

        $created = UserModel::where('email', 'invited@example.com')->firstOrFail();
        $this->assertTrue($created->roles()->where('slug', 'admin')->exists());
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'invited@example.com']);
        Notification::assertSentTo($created, UserInviteNotification::class);
        Event::assertNotDispatched(UserCreated::class);
    }

    public function test_user_created_event_triggers_notification(): void
    {
        Notification::fake();

        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();

        $this->postJson('/api/v1/users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret123',
        ], $this->tenantHeaders($tenant, $token));

        $created = UserModel::where('email', 'jane@example.com')->firstOrFail();
        Notification::assertSentTo($created, UserCreatedNotification::class);
    }

    // ── Failure paths ──

    public function test_duplicate_email_fails_validation(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();
        UserModel::factory()->forTenant($tenant)->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Another User',
            'email' => 'taken@example.com',
            'password' => 'secret123',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_non_admin_cannot_create_user(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithUser();

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret123',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(403);
    }

    public function test_validation_fails_with_missing_fields(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->postJson('/api/v1/users', [], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_validation_allows_missing_password_when_invite_is_sent(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'send_invite' => true,
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(201);
        $response->assertJsonMissingValidationErrors(['password']);
    }

    public function test_validation_rejects_unknown_role(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret123',
            'role' => 'owner',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['role']);
    }

    public function test_validation_fails_with_short_password(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'short',
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }
}
