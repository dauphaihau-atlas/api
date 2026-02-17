<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\UserImportModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Jobs\ProcessImportChunk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserImportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.imports_disk', 'local'));
        Queue::fake();
    }

    public function test_users_import_returns_401_when_unauthenticated(): void
    {
        $csv = "name,email,password\nAlice,alice@example.com,password123";
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $response = $this->postJson('/api/v1/users/import', [
            'file' => $file,
        ]);

        $response->assertStatus(401);
    }

    public function test_users_import_returns_403_when_authenticated_as_non_admin(): void
    {
        $user = UserModel::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;
        $csv = "name,email,password\nAlice,alice@example.com,password123";
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $response = $this->post('/api/v1/users/import', [
            'file' => $file,
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(403);
    }

    public function test_users_import_returns_422_when_file_missing_or_invalid(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->post('/api/v1/users/import', [], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);
    }

    public function test_users_import_returns_422_when_file_is_not_csv(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

        $response = $this->post('/api/v1/users/import', [
            'file' => $file,
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);
    }

    public function test_users_import_returns_202_and_dispatches_jobs(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;
        $csv = "name,email,password\nAlice One,alice@example.com,password123\nBob Two,bob@example.com,secret456";
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $response = $this->post('/api/v1/users/import', [
            'file' => $file,
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(202);
        $response->assertJsonStructure(['data' => ['id', 'status'], 'message']);
        $this->assertSame('processing', $response->json('data.status'));
        $this->assertNotNull($response->json('data.id'));

        // Verify file stored on disk
        $disk = Storage::disk(config('filesystems.imports_disk', 'local'));
        $files = $disk->files('imports');
        $this->assertCount(1, $files);

        // Verify import record created
        $this->assertDatabaseHas('user_imports', [
            'id' => $response->json('data.id'),
            'total_rows' => 2,
        ]);

        Queue::assertPushed(ProcessImportChunk::class, 1);
    }

    public function test_users_import_returns_422_for_invalid_headers(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;
        $csv = "foo,bar\nAlice,alice@example.com";
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $response = $this->post('/api/v1/users/import', [
            'file' => $file,
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('headers', strtolower($response->json('message')));
    }

    public function test_import_status_returns_progress(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $import = UserImportModel::create([
            'batch_id' => 'test-batch-id',
            'file_path' => 'imports/test.csv',
            'status' => 'processing',
            'total_rows' => 100,
            'processed_rows' => 50,
            'created_count' => 45,
            'updated_count' => 5,
            'errors' => [],
            'started_at' => now(),
        ]);

        $response = $this->get("/api/v1/users/import/{$import->id}/status", [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id', 'status', 'total_rows', 'processed_rows',
                'created', 'updated', 'errors', 'progress_percentage',
                'started_at', 'completed_at',
            ],
        ]);
        $this->assertSame('processing', $response->json('data.status'));
        $this->assertEquals(50, $response->json('data.progress_percentage'));
    }

    public function test_import_status_returns_404_for_nonexistent_import(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->get('/api/v1/users/import/999/status', [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(404);
    }

    public function test_import_status_returns_401_when_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/users/import/1/status');

        $response->assertStatus(401);
    }

    public function test_import_status_returns_403_when_non_admin(): void
    {
        $user = UserModel::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->get('/api/v1/users/import/1/status', [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(403);
    }
}
