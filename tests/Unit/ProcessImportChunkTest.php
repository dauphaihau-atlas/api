<?php

namespace Tests\Unit;

use App\Core\Application\Contracts\UserImportRepositoryInterface;
use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Infrastructure\Broadcasting\Events\ImportProgressUpdated;
use App\Infrastructure\Persistence\Eloquent\Models\UserImportModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Jobs\ProcessImportChunk;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProcessImportChunkTest extends TestCase
{
    use RefreshDatabase;

    private UserImportModel $import;

    protected function setUp(): void
    {
        parent::setUp();

        $this->import = UserImportModel::create([
            'batch_id' => 'test-batch',
            'file_path' => 'imports/test.csv',
            'status' => 'processing',
            'total_rows' => 10,
            'processed_rows' => 0,
            'created_count' => 0,
            'updated_count' => 0,
            'errors' => [],
        ]);
    }

    public function test_creates_new_users(): void
    {
        $rows = [
            ['name' => 'Alice', 'email' => 'alice@test.com', 'password' => 'password123'],
            ['name' => 'Bob', 'email' => 'bob@test.com', 'password' => 'password456'],
        ];

        $job = new ProcessImportChunk($this->import->id, $rows, 2);
        $job->handle(
            app(UserRepositoryInterface::class),
            app(UserImportRepositoryInterface::class)
        );

        $this->assertDatabaseHas('users', ['email' => 'alice@test.com', 'name' => 'Alice']);
        $this->assertDatabaseHas('users', ['email' => 'bob@test.com', 'name' => 'Bob']);

        $this->import->refresh();
        $this->assertSame(2, $this->import->processed_rows);
        $this->assertSame(2, $this->import->created_count);
        $this->assertSame(0, $this->import->updated_count);

        $alice = UserModel::where('email', 'alice@test.com')->first();
        $this->assertTrue(Hash::check('password123', $alice->password));
    }

    public function test_updates_existing_users(): void
    {
        UserModel::factory()->create([
            'email' => 'existing@test.com',
            'name' => 'Old Name',
        ]);

        $rows = [
            ['name' => 'New Name', 'email' => 'existing@test.com', 'password' => 'newpass123'],
        ];

        $job = new ProcessImportChunk($this->import->id, $rows, 2);
        $job->handle(
            app(UserRepositoryInterface::class),
            app(UserImportRepositoryInterface::class)
        );

        $user = UserModel::where('email', 'existing@test.com')->first();
        $this->assertSame('New Name', $user->name);

        $this->import->refresh();
        $this->assertSame(0, $this->import->created_count);
        $this->assertSame(1, $this->import->updated_count);
    }

    public function test_collects_validation_errors(): void
    {
        $rows = [
            ['name' => '', 'email' => 'valid@test.com', 'password' => 'password123'],
            ['name' => 'Valid', 'email' => 'not-an-email', 'password' => 'password123'],
            ['name' => 'Short', 'email' => 'short@test.com', 'password' => 'short'],
        ];

        $job = new ProcessImportChunk($this->import->id, $rows, 2);
        $job->handle(
            app(UserRepositoryInterface::class),
            app(UserImportRepositoryInterface::class)
        );

        $this->import->refresh();
        $this->assertSame(3, $this->import->processed_rows);
        $this->assertSame(0, $this->import->created_count);
        $this->assertCount(3, $this->import->errors);

        $rowNumbers = array_column($this->import->errors, 'row');
        $this->assertContains(2, $rowNumbers);
        $this->assertContains(3, $rowNumbers);
        $this->assertContains(4, $rowNumbers);
    }

    public function test_handles_mix_of_valid_and_invalid_rows(): void
    {
        $rows = [
            ['name' => 'Valid', 'email' => 'valid@test.com', 'password' => 'password123'],
            ['name' => '', 'email' => 'invalid@test.com', 'password' => 'password123'],
        ];

        $job = new ProcessImportChunk($this->import->id, $rows, 2);
        $job->handle(
            app(UserRepositoryInterface::class),
            app(UserImportRepositoryInterface::class)
        );

        $this->import->refresh();
        $this->assertSame(2, $this->import->processed_rows);
        $this->assertSame(1, $this->import->created_count);
        $this->assertCount(1, $this->import->errors);
        $this->assertDatabaseHas('users', ['email' => 'valid@test.com']);
    }

    public function test_continues_processing_when_progress_broadcast_fails(): void
    {
        $rows = [
            ['name' => 'Alice', 'email' => 'alice@test.com', 'password' => 'password123'],
        ];

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->withAnyArgs()
            ->andReturnNull()
            ->byDefault();
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(function (object $event): bool {
                return $event instanceof ImportProgressUpdated;
            })
            ->andThrow(new RuntimeException('Broadcast unavailable'));

        $originalDispatcher = $this->app->make(Dispatcher::class);
        $originalEventsBinding = $this->app->make('events');
        $this->app->instance(Dispatcher::class, $dispatcher);
        $this->app->instance('events', $dispatcher);

        try {
            $job = new ProcessImportChunk($this->import->id, $rows, 2);
            $job->handle(
                app(UserRepositoryInterface::class),
                app(UserImportRepositoryInterface::class)
            );
        } finally {
            $this->app->instance(Dispatcher::class, $originalDispatcher);
            $this->app->instance('events', $originalEventsBinding);
        }

        $this->assertDatabaseHas('users', ['email' => 'alice@test.com', 'name' => 'Alice']);

        $this->import->refresh();
        $this->assertSame(1, $this->import->processed_rows);
        $this->assertSame(1, $this->import->created_count);
        $this->assertSame(0, $this->import->updated_count);
    }
}
