<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserExportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.exports_disk', 'local'));
    }

    /**
     * Scenario: No token / invalid token.
     * Expectation: 401
     */
    public function test_users_export_returns_401_when_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/users/export');

        $response->assertStatus(401);
    }

    /**
     * Scenario: Authenticated as non-admin.
     * Expectation: 403
     */
    public function test_users_export_returns_403_when_authenticated_as_non_admin(): void
    {
        $user = UserModel::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->get('/api/v1/users/export', [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Scenario: Admin requests export.
     * Expectation: 200; JSON with path, url, and optionally expires_at; file on disk.
     */
    public function test_users_export_returns_200_with_path_and_url_when_admin(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->get('/api/v1/users/export', [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['path', 'url'],
        ]);
        $path = $response->json('data.path');
        $this->assertStringStartsWith('exports/users-', $path);
        $this->assertStringEndsWith('.csv', $path);

        $disk = Storage::disk(config('filesystems.exports_disk', 'local'));
        $this->assertTrue($disk->exists($path));
        $content = $disk->get($path);
        $this->assertStringContainsString('id,name,email,role,created_at', $content);
    }

    /**
     * Scenario: Admin exports; then follows signed download URL.
     * Expectation: Download returns 200 and CSV content with header and user row.
     */
    public function test_signed_download_returns_csv_content(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $exportResponse = $this->get('/api/v1/users/export', [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);
        $exportResponse->assertStatus(200);
        $url = $exportResponse->json('data.url');
        $this->assertNotEmpty($url);

        $downloadResponse = $this->get($url, [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'text/csv',
        ]);

        $downloadResponse->assertStatus(200);
        $this->assertStringContainsString('text/csv', $downloadResponse->headers->get('Content-Type') ?? '');
        $csv = $downloadResponse->streamedContent();
        $this->assertStringContainsString('id,name,email,role,created_at', $csv);
        $this->assertStringContainsString($admin->email, $csv);
    }
}
