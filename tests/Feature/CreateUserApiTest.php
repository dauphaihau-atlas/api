<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\UserCreated;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\UserCreatedNotification;
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
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(201);
        $response->assertJsonStructure(['data' => ['id', 'name', 'email', 'created_at']]);
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        Event::assertDispatched(UserCreated::class, fn (UserCreated $e) => $e->user->getEmail()->getValue() === 'jane@example.com');
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
