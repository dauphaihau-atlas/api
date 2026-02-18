<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_users_by_name(): void
    {
        $admin = UserModel::factory()->admin()->create(['name' => 'Admin Boss']);
        $token = $admin->createToken('test')->plainTextToken;

        UserModel::factory()->create(['name' => 'Alice Smith', 'email' => 'alice@example.com']);
        UserModel::factory()->create(['name' => 'Bob Jones', 'email' => 'bob@example.com']);

        $response = $this->getJson('/api/v1/users?search=Alice', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('Alice Smith', $response->json('data.0.name'));
    }

    public function test_search_users_by_email(): void
    {
        $admin = UserModel::factory()->admin()->create(['name' => 'Admin Boss']);
        $token = $admin->createToken('test')->plainTextToken;

        UserModel::factory()->create(['name' => 'Alice Smith', 'email' => 'alice@example.com']);
        UserModel::factory()->create(['name' => 'Bob Jones', 'email' => 'bob@example.com']);

        $response = $this->getJson('/api/v1/users?search=bob%40example', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('Bob Jones', $response->json('data.0.name'));
    }

    public function test_search_users_returns_empty_for_no_match(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        UserModel::factory()->create(['name' => 'Alice Smith']);

        $response = $this->getJson('/api/v1/users?search=nonexistent', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('meta.total'));
        $this->assertEmpty($response->json('data'));
    }

    public function test_empty_search_returns_all_users(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        UserModel::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/users?search=', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        // 3 created + 1 admin = 4
        $this->assertSame(4, $response->json('meta.total'));
    }

    public function test_search_without_param_returns_all_users(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        UserModel::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/users', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $this->assertSame(3, $response->json('meta.total'));
    }
}
