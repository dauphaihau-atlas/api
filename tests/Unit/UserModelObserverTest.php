<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Persistence\Eloquent\Models\ActivityLogModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class UserModelObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_logs_activity_with_causer(): void
    {
        $admin = UserModel::factory()->admin()->create();
        Auth::login($admin);

        $user = UserModel::factory()->create([
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'role' => 'user',
        ]);

        $this->assertDatabaseCount('activity_log', 2);

        $createdLog = ActivityLogModel::where('event', 'created')
            ->where('subject_id', $user->id)
            ->where('subject_type', UserModel::class)
            ->first();

        $this->assertNotNull($createdLog);
        $this->assertSame('created', $createdLog->event);
        $this->assertSame((string) $admin->id, (string) $createdLog->causer_id);
        $this->assertSame(UserModel::class, $createdLog->causer_type);
        $this->assertArrayHasKey('name', $createdLog->properties ?? []);
        $this->assertSame('New User', ($createdLog->properties ?? [])['name']);
        $this->assertArrayNotHasKey('password', $createdLog->properties ?? []);
    }

    public function test_updated_logs_activity_with_old_and_new_and_excludes_password(): void
    {
        $admin = UserModel::factory()->admin()->create();
        Auth::login($admin);

        $user = UserModel::factory()->create(['name' => 'Old Name', 'role' => 'user']);
        $originalId = $user->id;

        ActivityLogModel::query()->delete();
        $this->assertDatabaseCount('activity_log', 0);

        $user->name = 'New Name';
        $user->role = 'admin';
        $user->save();

        $updatedLog = ActivityLogModel::where('event', 'updated')
            ->where('subject_id', $originalId)
            ->first();

        $this->assertNotNull($updatedLog);
        $this->assertSame('updated', $updatedLog->event);
        $this->assertSame((string) $admin->id, (string) $updatedLog->causer_id);
        $this->assertArrayHasKey('old', $updatedLog->properties ?? []);
        $this->assertArrayHasKey('new', $updatedLog->properties ?? []);
        $this->assertSame('Old Name', ($updatedLog->properties['old'])['name']);
        $this->assertSame('New Name', ($updatedLog->properties['new'])['name']);
        $this->assertArrayNotHasKey('password', $updatedLog->properties['old'] ?? []);
        $this->assertArrayNotHasKey('password', $updatedLog->properties['new'] ?? []);
    }

    public function test_updated_skips_logging_when_no_meaningful_changes(): void
    {
        $admin = UserModel::factory()->admin()->create();
        Auth::login($admin);

        $user = UserModel::factory()->create(['name' => 'Same', 'email' => 'same@example.com']);
        ActivityLogModel::query()->delete();
        $this->assertDatabaseCount('activity_log', 0);

        $user->touch();

        $this->assertDatabaseCount('activity_log', 0);
    }

    public function test_deleted_logs_activity_with_subject_and_causer(): void
    {
        $admin = UserModel::factory()->admin()->create();
        Auth::login($admin);

        $user = UserModel::factory()->create(['name' => 'To Delete']);
        $userId = $user->id;
        ActivityLogModel::query()->delete();
        $this->assertDatabaseCount('activity_log', 0);

        $user->delete();

        $deletedLog = ActivityLogModel::where('event', 'deleted')
            ->where('subject_id', $userId)
            ->where('subject_type', UserModel::class)
            ->first();

        $this->assertNotNull($deletedLog);
        $this->assertSame('deleted', $deletedLog->event);
        $this->assertSame((string) $admin->id, (string) $deletedLog->causer_id);
    }

    public function test_activity_log_causer_null_when_unauthenticated(): void
    {
        $this->assertNull(Auth::user());

        $user = UserModel::factory()->create(['name' => 'No Causer']);

        $createdLog = ActivityLogModel::where('event', 'created')
            ->where('subject_id', $user->id)
            ->first();

        $this->assertNotNull($createdLog);
        $this->assertNull($createdLog->causer_id);
        $this->assertNull($createdLog->causer_type);
    }
}
