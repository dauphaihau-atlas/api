<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers:
 *   - V1 deprecation headers on every response
 *   - V2 running side-by-side (different response shape)
 *   - Transformation layer differences between v1 and v2
 */
class ApiVersioningTest extends TestCase
{
    use RefreshDatabase;

    // ── V1 deprecation headers ───────────────────────────────────────────────

    public function test_v1_responses_include_deprecation_header(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/users', ['Authorization' => 'Bearer '.$token]);

        $response->assertStatus(200);
        $response->assertHeader('Deprecation');
        $this->assertNotEmpty($response->headers->get('Deprecation'));
    }

    public function test_v1_responses_include_sunset_header(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/users', ['Authorization' => 'Bearer '.$token]);

        $response->assertStatus(200);
        $response->assertHeader('Sunset');
    }

    public function test_v1_responses_include_successor_version_link_header(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/users', ['Authorization' => 'Bearer '.$token]);

        $response->assertStatus(200);
        $linkHeader = $response->headers->get('Link');
        $this->assertStringContainsString('rel="successor-version"', $linkHeader);
        $this->assertStringContainsString('/api/v2/users', $linkHeader);
    }

    public function test_v2_responses_do_not_include_deprecation_headers(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v2/users', ['Authorization' => 'Bearer '.$token]);

        $response->assertStatus(200);
        $this->assertNull($response->headers->get('Deprecation'));
        $this->assertNull($response->headers->get('Sunset'));
    }

    // ── V2 runs side-by-side with V1 ────────────────────────────────────────

    public function test_v1_and_v2_user_list_both_return_200(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $this->getJson('/api/v1/users', ['Authorization' => 'Bearer '.$token])->assertStatus(200);
        $this->getJson('/api/v2/users', ['Authorization' => 'Bearer '.$token])->assertStatus(200);
    }

    public function test_v2_user_list_requires_authentication(): void
    {
        $this->getJson('/api/v2/users')->assertStatus(401);
    }

    public function test_v2_user_list_requires_admin_role(): void
    {
        $user = UserModel::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->getJson('/api/v2/users', ['Authorization' => 'Bearer '.$token])->assertStatus(403);
    }

    // ── Transformation layer — shape differences ─────────────────────────────

    public function test_v1_user_list_returns_avatar_url_as_string(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/users', ['Authorization' => 'Bearer '.$token]);

        $response->assertStatus(200);
        $user = $response->json('data.0');
        $this->assertArrayHasKey('avatar_url', $user);
        $this->assertArrayNotHasKey('avatar', $user);
    }

    public function test_v2_user_list_returns_avatar_as_object(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v2/users', ['Authorization' => 'Bearer '.$token]);

        $response->assertStatus(200);
        $user = $response->json('data.0');
        $this->assertArrayHasKey('avatar', $user);
        $this->assertIsArray($user['avatar']);
        $this->assertArrayHasKey('url', $user['avatar']);
        $this->assertArrayNotHasKey('avatar_url', $user);
    }

    public function test_v1_roles_are_an_array_of_strings(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/users', ['Authorization' => 'Bearer '.$token]);

        $response->assertStatus(200);
        $roles = $response->json('data.0.roles');
        $this->assertIsArray($roles);
        $this->assertIsString($roles[0]);
    }

    public function test_v2_roles_are_an_array_of_objects(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v2/users', ['Authorization' => 'Bearer '.$token]);

        $response->assertStatus(200);
        $roles = $response->json('data.0.roles');
        $this->assertIsArray($roles);
        $this->assertArrayHasKey('slug', $roles[0]);
        $this->assertArrayHasKey('name', $roles[0]);
    }

    public function test_v2_response_includes_updated_at(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v2/users', ['Authorization' => 'Bearer '.$token]);

        $response->assertStatus(200);
        $user = $response->json('data.0');
        $this->assertArrayHasKey('updated_at', $user);
    }

    public function test_v1_created_at_uses_legacy_format(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/users', ['Authorization' => 'Bearer '.$token]);

        $response->assertStatus(200);
        $createdAt = $response->json('data.0.created_at');
        // Matches "Y-m-d H:i:s" e.g. "2026-02-24 10:00:00"
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $createdAt);
    }

    public function test_v2_created_at_uses_iso8601_format(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v2/users', ['Authorization' => 'Bearer '.$token]);

        $response->assertStatus(200);
        $createdAt = $response->json('data.0.created_at');
        // ISO-8601 with timezone offset, e.g. "2026-02-24T10:00:00+00:00"
        $this->assertNotNull(DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $createdAt));
    }

    // ── V2 create user ───────────────────────────────────────────────────────

    public function test_v2_store_creates_user_and_returns_v2_shape(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->postJson('/api/v2/users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
        ], ['Authorization' => 'Bearer '.$token]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertArrayHasKey('avatar', $data);
        $this->assertIsArray($data['avatar']);
        $this->assertArrayHasKey('roles', $data);
        $this->assertMatchesRegularExpression('/T/', $data['created_at']); // ISO-8601 has T
    }
}
