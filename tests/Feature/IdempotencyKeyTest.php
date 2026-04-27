<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\IdempotencyKeyModel;
use App\Infrastructure\Persistence\Eloquent\Models\TenantModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Jobs\ProcessImportChunk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IdempotencyKeyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.imports_disk', 'local'));
        Storage::fake(config('filesystems.exports_disk', 'local'));
        Queue::fake();
    }

    // ── Header validation ─────────────────────────────────────────────────────

    public function test_import_returns_422_when_idempotency_key_is_missing(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();
        $file = UploadedFile::fake()->createWithContent('users.csv', "name,email,password\nAlice,alice@example.com,password123");

        $response = $this->post('/api/v1/users/import', ['file' => $file], $this->tenantHeaders($tenant, $token));

        $response->assertStatus(422);
        $response->assertJsonFragment(['error_code' => 'MISSING_IDEMPOTENCY_KEY']);
    }

    public function test_export_returns_422_when_idempotency_key_is_missing(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->get('/api/v1/users/export', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(422);
        $response->assertJsonFragment(['error_code' => 'MISSING_IDEMPOTENCY_KEY']);
    }

    public function test_key_shorter_than_16_chars_is_rejected(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();
        $file = UploadedFile::fake()->createWithContent('users.csv', "name,email,password\nAlice,a@a.com,password123");
        $headers = array_merge($this->tenantHeaders($tenant, $token), ['Idempotency-Key' => 'short']);

        $response = $this->post('/api/v1/users/import', ['file' => $file], $headers);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error_code' => 'INVALID_IDEMPOTENCY_KEY']);
    }

    public function test_key_with_invalid_characters_is_rejected(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();
        $file = UploadedFile::fake()->createWithContent('users.csv', "name,email,password\nAlice,a@a.com,password123");
        $headers = array_merge($this->tenantHeaders($tenant, $token), ['Idempotency-Key' => 'invalid/key/slashes!!!!!']);

        $response = $this->post('/api/v1/users/import', ['file' => $file], $headers);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error_code' => 'INVALID_IDEMPOTENCY_KEY']);
    }

    public function test_key_with_whitespace_is_rejected(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();
        $file = UploadedFile::fake()->createWithContent('users.csv', "name,email,password\nAlice,a@a.com,password123");
        $headers = array_merge($this->tenantHeaders($tenant, $token), ['Idempotency-Key' => 'key with spaces  here !!']);

        $response = $this->post('/api/v1/users/import', ['file' => $file], $headers);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error_code' => 'INVALID_IDEMPOTENCY_KEY']);
    }

    // ── First request creates operation normally ───────────────────────────────

    public function test_first_import_request_creates_import_normally(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();
        $csv = "name,email,password\nAlice,alice@example.com,password123";
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);
        $headers = array_merge($this->tenantHeaders($tenant, $token), ['Idempotency-Key' => $this->idempotencyKey()]);

        $response = $this->post('/api/v1/users/import', ['file' => $file], $headers);

        $response->assertStatus(202);
        $this->assertNotNull($response->json('data.id'));
        $this->assertDatabaseHas('idempotency_keys', [
            'idempotency_key' => $this->idempotencyKey(),
            'status' => 'completed',
            'response_status' => 202,
        ]);
    }

    // ── Duplicate request returns cached response ─────────────────────────────

    public function test_duplicate_import_returns_same_response(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();
        $csv = "name,email,password\nAlice,alice@example.com,password123";
        $file1 = UploadedFile::fake()->createWithContent('users.csv', $csv);
        $file2 = UploadedFile::fake()->createWithContent('users.csv', $csv);
        $headers = array_merge($this->tenantHeaders($tenant, $token), ['Idempotency-Key' => $this->idempotencyKey()]);

        $first = $this->post('/api/v1/users/import', ['file' => $file1], $headers);
        $second = $this->post('/api/v1/users/import', ['file' => $file2], $headers);

        $first->assertStatus(202);
        $second->assertStatus(202);
        $this->assertEquals($first->json('data.id'), $second->json('data.id'));
        $second->assertHeader('Idempotency-Replayed', 'true');
    }

    public function test_duplicate_import_does_not_dispatch_duplicate_jobs(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();
        $csv = "name,email,password\nAlice,alice@example.com,password123\nBob,bob@example.com,password456";
        $file1 = UploadedFile::fake()->createWithContent('users.csv', $csv);
        $file2 = UploadedFile::fake()->createWithContent('users.csv', $csv);
        $headers = array_merge($this->tenantHeaders($tenant, $token), ['Idempotency-Key' => $this->idempotencyKey()]);

        $this->post('/api/v1/users/import', ['file' => $file1], $headers);
        $this->post('/api/v1/users/import', ['file' => $file2], $headers);

        Queue::assertPushed(ProcessImportChunk::class, 1);
    }

    public function test_duplicate_export_returns_same_response(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();
        $headers = array_merge($this->tenantHeaders($tenant, $token), ['Idempotency-Key' => $this->idempotencyKey()]);

        $first = $this->get('/api/v1/users/export', $headers);
        $second = $this->get('/api/v1/users/export', $headers);

        $first->assertStatus(200);
        $second->assertStatus(200);
        $this->assertEquals($first->json('data.path'), $second->json('data.path'));
        $second->assertHeader('Idempotency-Replayed', 'true');
    }

    // ── Conflict on payload mismatch ──────────────────────────────────────────

    public function test_same_key_different_file_returns_409(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();
        $file1 = UploadedFile::fake()->createWithContent('users.csv', "name,email,password\nAlice,alice@example.com,password123");
        $file2 = UploadedFile::fake()->createWithContent('users.csv', "name,email,password\nBob,bob@example.com,password456");
        $headers = array_merge($this->tenantHeaders($tenant, $token), ['Idempotency-Key' => $this->idempotencyKey()]);

        $first = $this->post('/api/v1/users/import', ['file' => $file1], $headers);
        $second = $this->post('/api/v1/users/import', ['file' => $file2], $headers);

        $first->assertStatus(202);
        $second->assertStatus(409);
        $second->assertJsonFragment(['error_code' => 'IDEMPOTENCY_CONFLICT']);
    }

    // ── Scope isolation ───────────────────────────────────────────────────────

    public function test_same_key_is_independent_per_user(): void
    {
        $tenant = TenantModel::factory()->create();
        $admin1 = UserModel::factory()->admin()->forTenant($tenant)->create();
        $admin2 = UserModel::factory()->admin()->forTenant($tenant)->create();
        $key = $this->idempotencyKey();

        $csv1 = "name,email,password\nAlice,alice@example.com,password123";
        $csv2 = "name,email,password\nBob,bob@example.com,password456";
        $file1 = UploadedFile::fake()->createWithContent('users.csv', $csv1);
        $file2 = UploadedFile::fake()->createWithContent('users2.csv', $csv2);

        // Use actingAs to bypass Sanctum's session-based auth that persists between test requests
        $first = $this->actingAs($admin1, 'sanctum')
            ->withHeaders(['X-Tenant-ID' => $tenant->slug, 'Accept' => 'application/json', 'Idempotency-Key' => $key])
            ->post('/api/v1/users/import', ['file' => $file1]);

        $second = $this->actingAs($admin2, 'sanctum')
            ->withHeaders(['X-Tenant-ID' => $tenant->slug, 'Accept' => 'application/json', 'Idempotency-Key' => $key])
            ->post('/api/v1/users/import', ['file' => $file2]);

        $first->assertStatus(202);
        $second->assertStatus(202);
        // Each user gets their own idempotency scope — two separate records must exist
        $this->assertDatabaseCount('idempotency_keys', 2);
        // The second response must NOT be replayed from the first user's cache
        $second->assertHeaderMissing('Idempotency-Replayed');
    }

    public function test_same_key_is_independent_per_tenant(): void
    {
        $tenant1 = TenantModel::factory()->create();
        $tenant2 = TenantModel::factory()->create();
        $admin1 = UserModel::factory()->admin()->forTenant($tenant1)->create();
        $admin2 = UserModel::factory()->admin()->forTenant($tenant2)->create();
        $key = $this->idempotencyKey();

        $csv = "name,email,password\nAlice,alice@example.com,password123";
        $file1 = UploadedFile::fake()->createWithContent('users.csv', $csv);
        $file2 = UploadedFile::fake()->createWithContent('users2.csv', $csv);

        $first = $this->actingAs($admin1, 'sanctum')
            ->withHeaders(['X-Tenant-ID' => $tenant1->slug, 'Accept' => 'application/json', 'Idempotency-Key' => $key])
            ->post('/api/v1/users/import', ['file' => $file1]);

        $second = $this->actingAs($admin2, 'sanctum')
            ->withHeaders(['X-Tenant-ID' => $tenant2->slug, 'Accept' => 'application/json', 'Idempotency-Key' => $key])
            ->post('/api/v1/users/import', ['file' => $file2]);

        $first->assertStatus(202);
        $second->assertStatus(202);
        $this->assertDatabaseCount('idempotency_keys', 2);
        $second->assertHeaderMissing('Idempotency-Replayed');
    }

    // ── Expiry & pruning ──────────────────────────────────────────────────────

    public function test_prune_command_deletes_expired_records(): void
    {
        IdempotencyKeyModel::create([
            'user_id' => 1,
            'tenant_id' => null,
            'scope_hash' => hash('sha256', 'expired-scope-001'),
            'idempotency_key' => $this->idempotencyKey('expired-001'),
            'route_action' => 'TestController@action',
            'request_hash' => hash('sha256', 'request-hash-001'),
            'status' => 'completed',
            'expires_at' => now()->subHour(),
        ]);
        IdempotencyKeyModel::create([
            'user_id' => 1,
            'tenant_id' => null,
            'scope_hash' => hash('sha256', 'active-scope-001'),
            'idempotency_key' => $this->idempotencyKey('active-001'),
            'route_action' => 'TestController@action',
            'request_hash' => hash('sha256', 'request-hash-002'),
            'status' => 'completed',
            'expires_at' => now()->addHour(),
        ]);

        $this->artisan('app:prune-idempotency-keys')->assertExitCode(0);

        $this->assertDatabaseCount('idempotency_keys', 1);
        $this->assertDatabaseHas('idempotency_keys', ['idempotency_key' => $this->idempotencyKey('active-001')]);
    }

    public function test_prune_command_dry_run_does_not_delete(): void
    {
        IdempotencyKeyModel::create([
            'user_id' => 1,
            'tenant_id' => null,
            'scope_hash' => hash('sha256', 'expired-scope-dry'),
            'idempotency_key' => $this->idempotencyKey('expired-dry'),
            'route_action' => 'TestController@action',
            'request_hash' => hash('sha256', 'request-hash-dry'),
            'status' => 'completed',
            'expires_at' => now()->subHour(),
        ]);

        $this->artisan('app:prune-idempotency-keys --dry-run')->assertExitCode(0);

        $this->assertDatabaseCount('idempotency_keys', 1);
    }
}
