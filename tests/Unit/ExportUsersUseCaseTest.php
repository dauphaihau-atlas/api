<?php

namespace Tests\Unit;

use App\Core\Application\UseCases\User\ExportUsers\ExportUsersRequest;
use App\Core\Application\UseCases\User\ExportUsers\ExportUsersUseCase;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentUserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportUsersUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private ExportUsersUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.exports_disk', 'local'));
        $this->useCase = new ExportUsersUseCase(new EloquentUserRepository());
    }

    /**
     * Scenario: Given N users, export builds CSV with header and N data rows; file stored under exports/.
     */
    public function test_execute_writes_csv_with_users_and_correct_header(): void
    {
        UserModel::factory()->create([
            'name' => 'Alice',
            'email' => 'alice@test.com',
            'role' => 'user',
        ]);
        UserModel::factory()->create([
            'name' => 'Bob',
            'email' => 'bob@test.com',
            'role' => 'admin',
        ]);

        $response = $this->useCase->execute(new ExportUsersRequest());

        $this->assertStringStartsWith('exports/users-', $response->path);
        $this->assertStringEndsWith('.csv', $response->path);

        $disk = Storage::disk(config('filesystems.exports_disk', 'local'));
        $this->assertTrue($disk->exists($response->path));
        $content = $disk->get($response->path);

        $this->assertStringContainsString('id,name,email,role,created_at', $content);
        $this->assertStringContainsString('alice@test.com', $content);
        $this->assertStringContainsString('bob@test.com', $content);
        $this->assertStringContainsString('user', $content);
        $this->assertStringContainsString('admin', $content);

        $lines = explode("\n", trim($content));
        $this->assertCount(3, $lines); // header + 2 data rows
    }

    /**
     * Scenario: No users in DB.
     * Expectation: CSV with header only; file still written; path returned.
     */
    public function test_execute_writes_header_only_when_no_users(): void
    {
        $response = $this->useCase->execute(new ExportUsersRequest());

        $this->assertStringStartsWith('exports/users-', $response->path);
        $disk = Storage::disk(config('filesystems.exports_disk', 'local'));
        $this->assertTrue($disk->exists($response->path));
        $content = $disk->get($response->path);
        $this->assertSame("id,name,email,role,created_at", trim($content));
        $this->assertNull($response->url);
        $this->assertNull($response->expiresAt);
    }

    /**
     * Scenario: Local disk; use case returns null url and null expires_at.
     */
    public function test_execute_returns_null_url_and_expires_at_for_local_disk(): void
    {
        $response = $this->useCase->execute(new ExportUsersRequest());
        $this->assertNull($response->url);
        $this->assertNull($response->expiresAt);
        $this->assertNotEmpty($response->path);
    }

    /**
     * Scenario: CSV fields with comma are escaped.
     */
    public function test_execute_escapes_csv_fields_with_comma(): void
    {
        UserModel::factory()->create([
            'name' => 'Doe, Jane',
            'email' => 'jane@test.com',
            'role' => 'user',
        ]);

        $response = $this->useCase->execute(new ExportUsersRequest());
        $content = Storage::disk(config('filesystems.exports_disk', 'local'))->get($response->path);
        $this->assertStringContainsString('"Doe, Jane"', $content);
    }
}
