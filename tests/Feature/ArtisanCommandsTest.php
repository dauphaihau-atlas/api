<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Domain\Enums\ImportStatus;
use App\Infrastructure\Persistence\Eloquent\Models\ActivityLogModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserImportModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArtisanCommandsTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $overrides */
    private function makeImport(array $overrides = []): UserImportModel
    {
        $now = now()->toDateTimeString();
        $attrs = array_merge([
            'batch_id' => Str::uuid()->toString(),
            'file_path' => 'imports/file.csv',
            'status' => ImportStatus::Completed->value,
            'total_rows' => 10,
            'processed_rows' => 10,
            'created_count' => 10,
            'updated_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides);

        // Cast enum to string if caller passed the enum object.
        if ($attrs['status'] instanceof ImportStatus) {
            $attrs['status'] = $attrs['status']->value;
        }

        // JSON-encode array fields for the raw DB insert.
        if (isset($attrs['errors']) && is_array($attrs['errors'])) {
            $attrs['errors'] = json_encode($attrs['errors']);
        }

        DB::table('user_imports')->insert($attrs);

        return UserImportModel::latest('id')->first();
    }

    /** @param array<string, mixed> $overrides */
    private function makeActivityLog(array $overrides = []): ActivityLogModel
    {
        $now = now()->toDateTimeString();
        $attrs = array_merge([
            'log_name' => 'default',
            'event' => 'created',
            'subject_type' => 'App\Infrastructure\Persistence\Eloquent\Models\UserModel',
            'subject_id' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides);

        DB::table('activity_logs')->insert($attrs);

        return ActivityLogModel::latest('id')->first();
    }

    // -------------------------------------------------------------------------
    // app:prune-imports
    // -------------------------------------------------------------------------

    public function test_prune_imports_deletes_old_terminal_imports(): void
    {
        Storage::fake(config('filesystems.imports_disk', 'local'));
        $disk = Storage::disk(config('filesystems.imports_disk', 'local'));
        $disk->put('imports/old.csv', 'name,email,password');

        $this->makeImport([
            'file_path' => 'imports/old.csv',
            'status' => ImportStatus::Completed->value,
            'created_at' => now()->subDays(31),
            'updated_at' => now()->subDays(31),
        ]);

        $this->artisan('app:prune-imports', ['--days' => 30])->assertSuccessful();

        $this->assertDatabaseCount('user_imports', 0);
        $this->assertFalse($disk->exists('imports/old.csv'));
    }

    public function test_prune_imports_skips_recent_imports(): void
    {
        $this->makeImport(['created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)]);

        $this->artisan('app:prune-imports', ['--days' => 30])
            ->assertSuccessful()
            ->expectsOutput('No imports to prune.');

        $this->assertDatabaseCount('user_imports', 1);
    }

    public function test_prune_imports_dry_run_does_not_delete(): void
    {
        Storage::fake(config('filesystems.imports_disk', 'local'));
        $disk = Storage::disk(config('filesystems.imports_disk', 'local'));
        $disk->put('imports/old.csv', 'data');

        $this->makeImport([
            'file_path' => 'imports/old.csv',
            'status' => ImportStatus::Failed->value,
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ]);

        $this->artisan('app:prune-imports', ['--days' => 30, '--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseCount('user_imports', 1);
        $this->assertTrue($disk->exists('imports/old.csv'));
    }

    public function test_prune_imports_skips_active_imports(): void
    {
        $this->makeImport([
            'status' => ImportStatus::Processing->value,
            'created_at' => now()->subDays(31),
            'updated_at' => now()->subDays(31),
        ]);

        $this->artisan('app:prune-imports', ['--days' => 30])
            ->assertSuccessful()
            ->expectsOutput('No imports to prune.');

        $this->assertDatabaseCount('user_imports', 1);
    }

    // -------------------------------------------------------------------------
    // app:prune-activity-logs
    // -------------------------------------------------------------------------

    public function test_prune_activity_logs_deletes_old_entries(): void
    {
        $this->makeActivityLog(['created_at' => now()->subDays(91), 'updated_at' => now()->subDays(91)]);

        $this->artisan('app:prune-activity-logs', ['--days' => 90])->assertSuccessful();

        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_prune_activity_logs_keeps_recent_entries(): void
    {
        $this->makeActivityLog(['created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)]);

        $this->artisan('app:prune-activity-logs', ['--days' => 90])
            ->assertSuccessful()
            ->expectsOutput('No activity logs to prune.');

        $this->assertDatabaseCount('activity_logs', 1);
    }

    public function test_prune_activity_logs_dry_run_does_not_delete(): void
    {
        $this->makeActivityLog(['created_at' => now()->subDays(100), 'updated_at' => now()->subDays(100)]);

        $this->artisan('app:prune-activity-logs', ['--days' => 90, '--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseCount('activity_logs', 1);
    }

    // -------------------------------------------------------------------------
    // app:prune-password-resets
    // -------------------------------------------------------------------------

    public function test_prune_password_resets_deletes_expired_tokens(): void
    {
        DB::table('password_reset_tokens')->insert([
            'email' => 'old@example.com',
            'token' => 'abc123',
            'created_at' => now()->subHours(25),
        ]);

        $this->artisan('app:prune-password-resets', ['--hours' => 24])->assertSuccessful();

        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_prune_password_resets_keeps_fresh_tokens(): void
    {
        DB::table('password_reset_tokens')->insert([
            'email' => 'new@example.com',
            'token' => 'xyz789',
            'created_at' => now()->subHours(1),
        ]);

        $this->artisan('app:prune-password-resets', ['--hours' => 24])
            ->assertSuccessful()
            ->expectsOutput('No expired password reset tokens to prune.');

        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    // -------------------------------------------------------------------------
    // app:cancel-stale-imports
    // -------------------------------------------------------------------------

    public function test_cancel_stale_imports_marks_stuck_imports_as_failed(): void
    {
        $import = $this->makeImport([
            'status' => ImportStatus::Processing->value,
            'processed_rows' => 10,
            'created_at' => now()->subHours(3),
            'updated_at' => now()->subHours(3),
        ]);

        $this->artisan('app:cancel-stale-imports', ['--minutes' => 120])->assertSuccessful();

        $this->assertDatabaseHas('user_imports', [
            'id' => $import->id,
            'status' => ImportStatus::Failed->value,
        ]);
    }

    public function test_cancel_stale_imports_ignores_recently_updated_imports(): void
    {
        $import = $this->makeImport(['status' => ImportStatus::Processing->value]);

        $this->artisan('app:cancel-stale-imports', ['--minutes' => 120])
            ->assertSuccessful()
            ->expectsOutput('No stale imports found.');

        $this->assertDatabaseHas('user_imports', [
            'id' => $import->id,
            'status' => ImportStatus::Processing->value,
        ]);
    }

    public function test_cancel_stale_imports_dry_run_does_not_update(): void
    {
        $import = $this->makeImport([
            'status' => ImportStatus::Pending->value,
            'created_at' => now()->subHours(5),
            'updated_at' => now()->subHours(5),
        ]);

        $this->artisan('app:cancel-stale-imports', ['--minutes' => 120, '--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('user_imports', [
            'id' => $import->id,
            'status' => ImportStatus::Pending->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // app:retry-failed-imports
    // -------------------------------------------------------------------------

    public function test_retry_failed_imports_resets_recent_failures(): void
    {
        $import = $this->makeImport([
            'status' => ImportStatus::Failed->value,
            'processed_rows' => 5,
            'errors' => [['row' => 1, 'message' => 'Bad email']],
            'started_at' => now()->subMinutes(30),
            'completed_at' => now()->subMinutes(28),
        ]);

        $this->artisan('app:retry-failed-imports', ['--minutes' => 60])->assertSuccessful();

        $this->assertDatabaseHas('user_imports', [
            'id' => $import->id,
            'status' => ImportStatus::Pending->value,
            'processed_rows' => 0,
            'created_count' => 0,
        ]);
    }

    public function test_retry_failed_imports_ignores_old_failures(): void
    {
        $import = $this->makeImport([
            'status' => ImportStatus::Failed->value,
            'created_at' => now()->subHours(5),
            'updated_at' => now()->subHours(5),
        ]);

        $this->artisan('app:retry-failed-imports', ['--minutes' => 60])
            ->assertSuccessful()
            ->expectsOutput('No failed imports to retry.');

        $this->assertDatabaseHas('user_imports', [
            'id' => $import->id,
            'status' => ImportStatus::Failed->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // app:report-import-stats
    // -------------------------------------------------------------------------

    public function test_report_import_stats_shows_message_when_no_imports(): void
    {
        $this->artisan('app:report-import-stats', ['--days' => 30])
            ->assertSuccessful()
            ->expectsOutput('No imports found in the last 30 day(s).');
    }

    public function test_report_import_stats_outputs_table_with_imports(): void
    {
        $this->makeImport([
            'status' => ImportStatus::Completed->value,
            'total_rows' => 100,
            'processed_rows' => 100,
            'created_count' => 90,
            'updated_count' => 10,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);

        $this->artisan('app:report-import-stats', ['--days' => 30])
            ->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // app:cache-warm
    // -------------------------------------------------------------------------

    public function test_cache_warm_runs_successfully(): void
    {
        $this->artisan('app:cache-warm')
            ->assertSuccessful()
            ->expectsOutput('Warming cache...');
    }
}
