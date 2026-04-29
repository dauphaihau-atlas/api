<?php

namespace Tests\Unit;

use App\Core\Application\Services\UserImportChunkProcessor;
use App\Events\ImportChunkProcessed;
use App\Infrastructure\Persistence\Eloquent\Models\UserImportModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Jobs\ProcessImportChunk;
use App\Notifications\UserInviteNotification;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
        Notification::fake();

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
            ['name' => 'Alice', 'email' => 'alice@test.com', 'role' => 'user'],
            ['name' => 'Bob', 'email' => 'bob@test.com', 'role' => 'admin'],
        ];

        $job = new ProcessImportChunk($this->import->id, $rows, 2);
        $job->handle(app(UserImportChunkProcessor::class));

        $this->assertDatabaseHas('users', ['email' => 'alice@test.com', 'name' => 'Alice']);
        $this->assertDatabaseHas('users', ['email' => 'bob@test.com', 'name' => 'Bob']);

        $this->import->refresh();
        $this->assertSame(2, $this->import->processed_rows);
        $this->assertSame(2, $this->import->created_count);
        $this->assertSame(0, $this->import->updated_count);

        $alice = UserModel::where('email', 'alice@test.com')->firstOrFail();
        $bob = UserModel::where('email', 'bob@test.com')->firstOrFail();
        $this->assertTrue($alice->roles->contains('slug', 'user'));
        $this->assertTrue($bob->roles->contains('slug', 'admin'));
        Notification::assertSentTo($alice, UserInviteNotification::class);
        Notification::assertSentTo($bob, UserInviteNotification::class);
    }

    public function test_updates_existing_users(): void
    {
        UserModel::factory()->create([
            'email' => 'existing@test.com',
            'name' => 'Old Name',
        ]);

        $rows = [
            ['name' => 'New Name', 'email' => 'existing@test.com', 'role' => 'admin'],
        ];

        $job = new ProcessImportChunk($this->import->id, $rows, 2);
        $job->handle(app(UserImportChunkProcessor::class));

        $user = UserModel::where('email', 'existing@test.com')->first();
        $this->assertSame('New Name', $user->name);
        $this->assertTrue($user->roles->contains('slug', 'admin'));

        $this->import->refresh();
        $this->assertSame(0, $this->import->created_count);
        $this->assertSame(1, $this->import->updated_count);
    }

    public function test_collects_validation_errors(): void
    {
        $rows = [
            ['name' => '', 'email' => 'valid@test.com', 'role' => 'user'],
            ['name' => 'Valid', 'email' => 'not-an-email', 'role' => 'user'],
            ['name' => 'Unsupported', 'email' => 'role@test.com', 'role' => 'owner'],
        ];

        $job = new ProcessImportChunk($this->import->id, $rows, 2);
        $job->handle(app(UserImportChunkProcessor::class));

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
            ['name' => 'Valid', 'email' => 'valid@test.com', 'role' => 'user'],
            ['name' => '', 'email' => 'invalid@test.com', 'role' => 'user'],
        ];

        $job = new ProcessImportChunk($this->import->id, $rows, 2);
        $job->handle(app(UserImportChunkProcessor::class));

        $this->import->refresh();
        $this->assertSame(2, $this->import->processed_rows);
        $this->assertSame(1, $this->import->created_count);
        $this->assertCount(1, $this->import->errors);
        $this->assertDatabaseHas('users', ['email' => 'valid@test.com']);
    }

    public function test_continues_processing_when_progress_broadcast_fails(): void
    {
        $rows = [
            ['name' => 'Alice', 'email' => 'alice@test.com', 'role' => 'user'],
        ];

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->withAnyArgs()
            ->andReturnNull()
            ->byDefault();
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(function (object $event): bool {
                return $event instanceof ImportChunkProcessed;
            })
            ->andThrow(new RuntimeException('Broadcast unavailable'));

        $originalDispatcher = $this->app->make(Dispatcher::class);
        $originalEventsBinding = $this->app->make('events');
        $this->app->instance(Dispatcher::class, $dispatcher);
        $this->app->instance('events', $dispatcher);

        try {
            $job = new ProcessImportChunk($this->import->id, $rows, 2);
            $job->handle(app(UserImportChunkProcessor::class));
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
