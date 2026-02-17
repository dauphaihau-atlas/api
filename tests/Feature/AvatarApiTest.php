<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.avatars_disk', 'public'));
    }

    /**
     * Scenario: No token / invalid token.
     * Expectation: 401
     */
    public function test_me_avatar_returns_401_when_unauthenticated(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $response = $this->postJson('/api/v1/me/avatar', [
            'avatar' => $file,
        ]);

        $response->assertStatus(401);
    }

    /**
     * Scenario: Authenticated, valid image (multipart).
     * Expectation: 200; JSON has avatar_url; Storage disk contains new file; user's avatar_path updated in DB.
     */
    public function test_me_avatar_returns_200_and_avatar_url_when_authenticated_with_valid_image(): void
    {
        $user = UserModel::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $response = $this->post('/api/v1/me/avatar', [
            'avatar' => $file,
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['id', 'name', 'email', 'avatar_url', 'created_at']]);
        $this->assertNotEmpty($response->json('data.avatar_url'));

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        $disk = Storage::disk(config('filesystems.avatars_disk', 'public'));
        $this->assertTrue($disk->exists($user->avatar_path));
    }

    /**
     * Scenario: Authenticated, missing file or invalid (e.g. text file, too large).
     * Expectation: 422 validation error.
     */
    public function test_me_avatar_returns_422_when_file_missing_or_invalid(): void
    {
        $user = UserModel::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->post('/api/v1/me/avatar', [], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Scenario: Authenticated as non-admin.
     * Expectation: 403
     */
    public function test_users_user_avatar_returns_403_when_authenticated_as_non_admin(): void
    {
        $regularUser = UserModel::factory()->create(['role' => 'user']);
        $targetUser = UserModel::factory()->create();
        $token = $regularUser->createToken('test')->plainTextToken;
        $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $response = $this->post("/api/v1/users/{$targetUser->id}/avatar", [
            'avatar' => $file,
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Scenario: Authenticated as admin, valid image.
     * Expectation: 200; target user has avatar_path set and avatar_url in response.
     */
    public function test_users_user_avatar_returns_200_and_sets_avatar_when_authenticated_as_admin(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $targetUser = UserModel::factory()->create();
        $token = $admin->createToken('test')->plainTextToken;
        $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $response = $this->post("/api/v1/users/{$targetUser->id}/avatar", [
            'avatar' => $file,
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['id', 'name', 'email', 'avatar_url', 'created_at']]);
        $response->assertJsonPath('data.id', $targetUser->id);
        $this->assertNotEmpty($response->json('data.avatar_url'));

        $targetUser->refresh();
        $this->assertNotNull($targetUser->avatar_path);
        $disk = Storage::disk(config('filesystems.avatars_disk', 'public'));
        $this->assertTrue($disk->exists($targetUser->avatar_path));
    }
}
