<?php

namespace Tests\Feature;

use App\Core\Application\UseCases\User\ImportUsers\ImportUsersRequest;
use App\Core\Application\UseCases\User\ImportUsers\ImportUsersUseCase;
use App\Core\Domain\Entities\Tenant;
use App\Infrastructure\Persistence\Eloquent\Models\TenantModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserImportModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Tenant\TenantContext;
use App\Jobs\ProcessImportChunk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LargeImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.imports_disk' => 'local']);
    }

    /**
     * Test dispatching 1M user import — verifies streaming, chunking, and dispatch
     * without OOM. Uses real queue (database driver) to avoid Queue::fake() memory overhead.
     */
    public function test_dispatch_1m_users_import(): void
    {
        $csvPath = storage_path('app/private/imports/1m-users.csv');
        $this->assertFileExists($csvPath, 'Run: cp ../csv-files-to-test/1m-users.csv storage/app/private/imports/');

        // Use database queue (real dispatch) — Queue::fake() would OOM storing 2000 job objects
        config(['queue.default' => 'database']);

        $tenant = TenantModel::factory()->create();

        // Seed the TenantContext so the use case can read the tenant ID
        $tenantContext = app(TenantContext::class);
        $tenantContext->set(new Tenant(
            id: $tenant->id,
            name: $tenant->name,
            slug: $tenant->slug,
        ));

        $useCase = app(ImportUsersUseCase::class);

        $memBefore = memory_get_usage(true);
        $start = microtime(true);
        $response = $useCase->execute(new ImportUsersRequest('imports/1m-users.csv', 'laravel'));
        $elapsed = round(microtime(true) - $start, 2);
        $memPeak = memory_get_peak_usage(true);
        $memUsedMb = round(($memPeak - $memBefore) / 1024 / 1024, 1);

        echo "\n--- 1M Import Dispatch Results ---\n";
        echo "Status: {$response->status}\n";
        echo "Import ID: {$response->importId}\n";
        echo "Dispatch time: {$elapsed}s\n";
        echo "Peak memory delta: ~{$memUsedMb} MB\n";

        $this->assertSame('processing', $response->status, "Message: {$response->message}");
        $this->assertNotNull($response->importId);

        $import = UserImportModel::find($response->importId);
        echo "Total rows: {$import->total_rows}\n";
        $this->assertSame(1_000_000, $import->total_rows);

        // Verify 2000 chunk jobs were queued in the database
        $jobCount = DB::table('jobs')->count();
        echo "Jobs in queue (DB): {$jobCount}\n";
        $this->assertSame(2000, $jobCount);

        // Verify batch record was created
        $batchCount = DB::table('job_batches')->count();
        echo "Batch records: {$batchCount}\n";
        $this->assertSame(1, $batchCount);

        echo "--- Done ---\n";
    }

    /**
     * Test actually processing a single chunk (10 rows) from the real CSV end-to-end.
     */
    public function test_process_single_chunk_from_1m_file(): void
    {
        $csvPath = storage_path('app/private/imports/1m-users.csv');
        $this->assertFileExists($csvPath);

        $stream = fopen($csvPath, 'r');
        fgetcsv($stream); // skip header
        $rows = [];
        for ($i = 0; $i < 10; $i++) {
            $row = fgetcsv($stream);
            if ($row === false) {
                break;
            }
            $rows[] = ['name' => $row[0], 'email' => $row[1], 'password' => $row[2]];
        }
        fclose($stream);

        $this->assertCount(10, $rows);

        $tenant = TenantModel::factory()->create();

        $import = UserImportModel::create([
            'tenant_id' => $tenant->id,
            'batch_id' => 'test-batch',
            'file_path' => 'imports/1m-users.csv',
            'status' => 'processing',
            'total_rows' => 10,
        ]);

        $job = new ProcessImportChunk($import->id, $rows, 2);
        $job->handle(
            app(\App\Core\Application\Contracts\UserRepositoryInterface::class),
            app(\App\Core\Application\Contracts\UserImportRepositoryInterface::class)
        );

        $import->refresh();
        echo "\n--- Single Chunk Processing ---\n";
        echo "Processed: {$import->processed_rows}\n";
        echo "Created: {$import->created_count}\n";
        echo "Updated: {$import->updated_count}\n";
        echo 'Errors: '.count($import->errors ?? [])."\n";

        $this->assertSame(10, $import->processed_rows);
        $this->assertSame(10, $import->created_count);
        $this->assertSame(0, $import->updated_count);
        $this->assertEmpty($import->errors);

        $this->assertSame(10, UserModel::count());
        $this->assertDatabaseHas('users', ['email' => 'user1@example.com', 'name' => 'User 1']);
        $this->assertDatabaseHas('users', ['email' => 'user10@example.com', 'name' => 'User 10']);

        echo "--- Done ---\n";
    }
}
