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
        ['tenant' => $tenant, 'user' => $user, 'token' => $token] = $this->createTenantWithUser();

        $response = $this->get('/api/v1/users/export', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(403);
    }

    /**
     * Scenario: Admin requests export.
     * Expectation: 200; JSON with path, url, and optionally expires_at; file on disk.
     */
    public function test_users_export_returns_200_with_path_and_url_when_admin(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->get('/api/v1/users/export', $this->tenantHeaders($tenant, $token));

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
        $this->assertStringContainsString('id,name,email,roles,created_at', $content);
    }

    /**
     * Scenario: Admin exports; then follows signed download URL.
     * Expectation: Download returns 200 and CSV content with header and user row.
     */
    public function test_signed_download_returns_csv_content(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        $exportResponse = $this->get('/api/v1/users/export', $this->tenantHeaders($tenant, $token));
        $exportResponse->assertStatus(200);
        $url = $exportResponse->json('data.url');
        $this->assertNotEmpty($url);

        $downloadResponse = $this->get($url, array_merge($this->tenantHeaders($tenant, $token), [
            'Accept' => 'text/csv',
        ]));

        $downloadResponse->assertStatus(200);
        $this->assertStringContainsString('text/csv', $downloadResponse->headers->get('Content-Type') ?? '');
        $csv = $downloadResponse->streamedContent();
        $this->assertStringContainsString('id,name,email,roles,created_at', $csv);
        $this->assertStringContainsString($admin->email, $csv);
    }

    // --- Date range filter tests ---

    public function test_export_filters_by_date_from(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        $old = UserModel::factory()->create([
            'tenant_id' => $tenant->id,
            'created_at' => '2024-06-01 10:00:00',
        ]);
        $new = UserModel::factory()->create([
            'tenant_id' => $tenant->id,
            'created_at' => '2025-03-01 10:00:00',
        ]);

        $response = $this->get(
            '/api/v1/users/export?date_from=2025-01-01',
            $this->tenantHeaders($tenant, $token)
        );

        $response->assertStatus(200);
        $path = $response->json('data.path');
        $csv = Storage::disk(config('filesystems.exports_disk', 'local'))->get($path);

        $this->assertStringContainsString($new->email, $csv);
        $this->assertStringNotContainsString($old->email, $csv);
    }

    public function test_export_filters_by_date_to(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        $old = UserModel::factory()->create([
            'tenant_id' => $tenant->id,
            'created_at' => '2024-06-01 10:00:00',
        ]);
        $new = UserModel::factory()->create([
            'tenant_id' => $tenant->id,
            'created_at' => '2025-03-01 10:00:00',
        ]);

        $response = $this->get(
            '/api/v1/users/export?date_to=2024-12-31',
            $this->tenantHeaders($tenant, $token)
        );

        $response->assertStatus(200);
        $path = $response->json('data.path');
        $csv = Storage::disk(config('filesystems.exports_disk', 'local'))->get($path);

        $this->assertStringContainsString($old->email, $csv);
        $this->assertStringNotContainsString($new->email, $csv);
    }

    public function test_export_filters_by_date_range(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        $before = UserModel::factory()->create([
            'tenant_id' => $tenant->id,
            'created_at' => '2024-01-01 00:00:00',
        ]);
        $within = UserModel::factory()->create([
            'tenant_id' => $tenant->id,
            'created_at' => '2024-06-15 12:00:00',
        ]);
        $after = UserModel::factory()->create([
            'tenant_id' => $tenant->id,
            'created_at' => '2025-01-01 00:00:00',
        ]);

        $response = $this->get(
            '/api/v1/users/export?date_from=2024-01-01&date_to=2024-12-31',
            $this->tenantHeaders($tenant, $token)
        );

        $response->assertStatus(200);
        $path = $response->json('data.path');
        $csv = Storage::disk(config('filesystems.exports_disk', 'local'))->get($path);

        $this->assertStringContainsString($within->email, $csv);
        $this->assertStringNotContainsString($after->email, $csv);
    }

    public function test_export_returns_422_for_invalid_date_format(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->getJson(
            '/api/v1/users/export?date_from=01-01-2025',
            $this->tenantHeaders($tenant, $token)
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date_from']);
    }

    public function test_export_returns_422_when_date_to_before_date_from(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->getJson(
            '/api/v1/users/export?date_from=2025-06-01&date_to=2025-01-01',
            $this->tenantHeaders($tenant, $token)
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date_to']);
    }

    // --- Field selection tests ---

    public function test_export_includes_only_selected_fields(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->get(
            '/api/v1/users/export?fields[]=id&fields[]=email',
            $this->tenantHeaders($tenant, $token)
        );

        $response->assertStatus(200);
        $path = $response->json('data.path');
        $csv = Storage::disk(config('filesystems.exports_disk', 'local'))->get($path);
        $header = strtok($csv, "\n");

        $this->assertSame('id,email', $header);
        $this->assertStringNotContainsString('name', $csv);
        $this->assertStringNotContainsString('roles', $csv);
        $this->assertStringNotContainsString('created_at', $csv);
    }

    public function test_export_returns_422_for_invalid_field_name(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->getJson(
            '/api/v1/users/export?fields[]=invalid_field',
            $this->tenantHeaders($tenant, $token)
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['fields.0']);
    }

    public function test_export_returns_all_fields_when_fields_param_omitted(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->createTenantWithAdmin();

        $response = $this->get('/api/v1/users/export', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $path = $response->json('data.path');
        $csv = Storage::disk(config('filesystems.exports_disk', 'local'))->get($path);
        $header = strtok($csv, "\n");

        $this->assertSame('id,name,email,roles,created_at', $header);
    }
}
