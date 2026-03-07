<?php

namespace Tests\Unit;

use App\Core\Application\UseCases\User\ImportUsers\ImportUsersRequest;
use App\Core\Application\UseCases\User\ImportUsers\ImportUsersUseCase;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentUserImportRepository;
use App\Infrastructure\Tenant\TenantContext;
use App\Jobs\ProcessImportChunk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportUsersUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private ImportUsersUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.imports_disk', 'local'));
        Queue::fake();
        $this->useCase = new ImportUsersUseCase(
            new EloquentUserImportRepository(new TenantContext),
            new TenantContext,
        );
    }

    public function test_execute_returns_failed_for_empty_file(): void
    {
        Storage::disk(config('filesystems.imports_disk', 'local'))->put('imports/empty.csv', '');

        $response = $this->useCase->execute(new ImportUsersRequest('imports/empty.csv'));

        $this->assertSame('failed', $response->status);
        $this->assertNull($response->importId);
    }

    public function test_execute_returns_failed_when_only_headers(): void
    {
        $csv = "name,email,password\n";
        Storage::disk(config('filesystems.imports_disk', 'local'))->put('imports/headers_only.csv', $csv);

        $response = $this->useCase->execute(new ImportUsersRequest('imports/headers_only.csv'));

        $this->assertSame('failed', $response->status);
        $this->assertNull($response->importId);
        $this->assertStringContainsString('no data', strtolower($response->message));
    }

    public function test_execute_dispatches_jobs_for_valid_csv(): void
    {
        $csv = "name,email,password\nAlice,alice@test.com,password123\nBob,bob@test.com,secret456";
        Storage::disk(config('filesystems.imports_disk', 'local'))->put('imports/valid.csv', $csv);

        $response = $this->useCase->execute(new ImportUsersRequest('imports/valid.csv'));

        $this->assertSame('processing', $response->status);
        $this->assertNotNull($response->importId);

        Queue::assertPushed(ProcessImportChunk::class, 1);
        Queue::assertPushed(ProcessImportChunk::class, function (ProcessImportChunk $job) {
            return count($job->rows) === 2 && $job->importId > 0;
        });
    }

    public function test_execute_creates_import_record(): void
    {
        $csv = "name,email,password\nAlice,alice@test.com,password123";
        Storage::disk(config('filesystems.imports_disk', 'local'))->put('imports/record.csv', $csv);

        $response = $this->useCase->execute(new ImportUsersRequest('imports/record.csv'));

        $this->assertDatabaseHas('user_imports', [
            'id' => $response->importId,
            'total_rows' => 1,
            'status' => 'processing',
        ]);
    }

    public function test_execute_creates_multiple_chunks_for_large_csv(): void
    {
        $csv = "name,email,password\n";
        for ($i = 1; $i <= 1200; $i++) {
            $csv .= "User{$i},user{$i}@test.com,password123\n";
        }
        Storage::disk(config('filesystems.imports_disk', 'local'))->put('imports/large.csv', $csv);

        $response = $this->useCase->execute(new ImportUsersRequest('imports/large.csv'));

        $this->assertSame('processing', $response->status);

        // 1200 rows / 500 chunk size = 3 chunks (500 + 500 + 200)
        Queue::assertPushed(ProcessImportChunk::class, 3);

        $this->assertDatabaseHas('user_imports', [
            'id' => $response->importId,
            'total_rows' => 1200,
        ]);
    }

    public function test_execute_returns_failed_when_headers_invalid(): void
    {
        $csv = "foo,bar\nAlice,alice@test.com";
        Storage::disk(config('filesystems.imports_disk', 'local'))->put('imports/bad_headers.csv', $csv);

        $response = $this->useCase->execute(new ImportUsersRequest('imports/bad_headers.csv'));

        $this->assertSame('failed', $response->status);
        $this->assertNull($response->importId);
        $this->assertStringContainsString('headers', strtolower($response->message));

        Queue::assertNothingPushed();
    }
}
