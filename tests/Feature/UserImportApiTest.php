<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\UserImportModel;
use App\Jobs\ProcessImportChunk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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
        $csv = "name,email,role\nAlice,alice@example.com,user";
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $response = $this->postJson('/api/v1/users/import', [
            'file' => $file,
        ]);

        $response->assertStatus(401);
    }

    public function test_users_import_returns_403_when_authenticated_as_non_admin(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'token' => $token] = $this->createTenantWithUser();
        $csv = "name,email,role\nAlice,alice@example.com,user";
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $response = $this->post('/api/v1/users/import', [
            'file' => $file,
        ], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(403);
    }

    public function test_users_import_returns_422_when_file_missing_or_invalid(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        $headers = array_merge($this->tenantHeaders($tenant, $token), ['Idempotency-Key' => $this->idempotencyKey()]);

        $response = $this->post('/api/v1/users/import', [], $headers);

        $response->assertStatus(422);
    }

    public function test_users_import_returns_422_when_file_is_not_csv(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);
        $headers = array_merge($this->tenantHeaders($tenant, $token), ['Idempotency-Key' => $this->idempotencyKey()]);

        $response = $this->post('/api/v1/users/import', ['file' => $file], $headers);

        $response->assertStatus(422);
    }

    public function test_users_import_with_laravel_processor_returns_202_and_dispatches_jobs(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        $csv = "name,email,role\nAlice One,alice@example.com,user\nBob Two,bob@example.com,admin";
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);
        $headers = array_merge($this->tenantHeaders($tenant, $token), ['Idempotency-Key' => $this->idempotencyKey()]);

        $response = $this->post('/api/v1/users/import?processor=laravel', ['file' => $file], $headers);

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

    public function test_users_import_defaults_to_go_processor(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        config(['services.go_worker.url' => 'http://localhost:8081']);
        Http::fake([
            'http://localhost:8081/user-imports' => Http::response(['status' => 'accepted'], 202),
        ]);

        $csv = "name,email,role\nAlice One,alice@example.com,user";
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);
        $headers = array_merge($this->tenantHeaders($tenant, $token), ['Idempotency-Key' => $this->idempotencyKey()]);

        $response = $this->post('/api/v1/users/import', ['file' => $file], $headers);

        $response->assertStatus(202);
        $response->assertJsonPath('data.status', 'processing');
        $response->assertJsonPath('data.processor', 'go');

        $this->assertDatabaseHas('user_imports', [
            'id' => $response->json('data.id'),
            'processor' => 'go',
            'status' => 'processing',
        ]);

        Queue::assertNothingPushed();
        Http::assertSent(fn ($request) => $request->url() === 'http://localhost:8081/user-imports'
            && $request['import_id'] === $response->json('data.id')
            && $request['tenant_id'] === $tenant->id);
    }

    public function test_users_import_returns_422_for_invalid_processor(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        $csv = "name,email,role\nAlice One,alice@example.com,user";
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);
        $headers = array_merge($this->tenantHeaders($tenant, $token), ['Idempotency-Key' => $this->idempotencyKey()]);

        $response = $this->post('/api/v1/users/import?processor=bad', ['file' => $file], $headers);

        $response->assertStatus(422);
    }

    public function test_users_import_returns_422_for_invalid_headers(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        $csv = "foo,bar\nAlice,alice@example.com";
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);
        $headers = array_merge($this->tenantHeaders($tenant, $token), ['Idempotency-Key' => $this->idempotencyKey()]);

        $response = $this->post('/api/v1/users/import?processor=laravel', ['file' => $file], $headers);

        $response->assertStatus(422);
        $this->assertStringContainsString('headers', strtolower($response->json('message')));
    }

    public function test_import_status_returns_progress(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        $import = UserImportModel::create([
            'tenant_id' => $tenant->id,
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

        $response = $this->get("/api/v1/users/import/{$import->id}/status", $this->tenantHeaders($tenant, $token));

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
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->get('/api/v1/users/import/999/status', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(404);
    }

    public function test_import_status_returns_401_when_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/users/import/1/status');

        $response->assertStatus(401);
    }

    public function test_import_status_returns_403_when_non_admin(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'token' => $token] = $this->createTenantWithUser();

        $response = $this->get('/api/v1/users/import/1/status', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(403);
    }

    public function test_cancel_import_returns_200_when_processing(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        $import = UserImportModel::create([
            'tenant_id' => $tenant->id,
            'batch_id' => 'test-batch-cancel',
            'file_path' => 'imports/test.csv',
            'status' => 'processing',
            'total_rows' => 100,
            'processed_rows' => 10,
            'created_count' => 10,
            'updated_count' => 0,
            'errors' => [],
            'started_at' => now(),
        ]);

        $response = $this->delete("/api/v1/users/import/{$import->id}", [], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'cancelled');
        $response->assertJsonPath('data.id', $import->id);
        $this->assertDatabaseHas('user_imports', ['id' => $import->id, 'status' => 'cancelled']);
    }

    public function test_cancel_import_returns_200_when_pending(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        $import = UserImportModel::create([
            'tenant_id' => $tenant->id,
            'batch_id' => 'test-batch-pending',
            'file_path' => 'imports/test.csv',
            'status' => 'pending',
            'total_rows' => 50,
            'processed_rows' => 0,
            'created_count' => 0,
            'updated_count' => 0,
            'errors' => [],
        ]);

        $response = $this->delete("/api/v1/users/import/{$import->id}", [], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'cancelled');
        $this->assertDatabaseHas('user_imports', ['id' => $import->id, 'status' => 'cancelled']);
    }

    public function test_cancel_import_returns_404_for_nonexistent_import(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->delete('/api/v1/users/import/999', [], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(404);
    }

    public function test_cancel_import_returns_409_when_already_completed(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        $import = UserImportModel::create([
            'tenant_id' => $tenant->id,
            'batch_id' => 'test-batch-done',
            'file_path' => 'imports/test.csv',
            'status' => 'completed',
            'total_rows' => 10,
            'processed_rows' => 10,
            'created_count' => 10,
            'updated_count' => 0,
            'errors' => [],
            'completed_at' => now(),
        ]);

        $response = $this->delete("/api/v1/users/import/{$import->id}", [], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(409);
    }

    public function test_cancel_import_returns_401_when_unauthenticated(): void
    {
        $response = $this->deleteJson('/api/v1/users/import/1');

        $response->assertStatus(401);
    }

    public function test_cancel_import_returns_403_when_non_admin(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'token' => $token] = $this->createTenantWithUser();

        $response = $this->delete('/api/v1/users/import/1', [], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(403);
    }
}
