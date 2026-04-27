<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Domain\Enums\ImportStatus;
use App\Infrastructure\Persistence\Eloquent\Models\UserImportModel;
use Illuminate\Console\Command;

class RetryFailedImports extends Command
{
    protected $signature = 'app:retry-failed-imports
                            {--minutes=60 : Only retry imports that failed within this many minutes}
                            {--dry-run : Preview which imports would be retried without making changes}';

    protected $description = 'Reset failed import records to pending so they are picked up again by the queue';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $dryRun = (bool) $this->option('dry-run');
        $since = now()->subMinutes($minutes);

        $imports = UserImportModel::query()
            ->where('status', ImportStatus::Failed->value)
            ->where('updated_at', '>=', $since)
            ->get();

        if ($imports->isEmpty()) {
            $this->info('No failed imports to retry.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s %d failed import(s) that failed within the last %d minute(s).',
            $dryRun ? '[dry-run] Would reset' : 'Resetting',
            $imports->count(),
            $minutes,
        ));

        foreach ($imports as $import) {
            $this->line(sprintf(
                '  ID: %d | rows: %d | failed at: %s',
                $import->id,
                $import->total_rows,
                $import->updated_at,
            ));

            if (! $dryRun) {
                UserImportModel::where('id', $import->id)->update([
                    'status' => ImportStatus::Pending->value,
                    'processed_rows' => 0,
                    'created_count' => 0,
                    'updated_count' => 0,
                    'errors' => null,
                    'started_at' => null,
                    'completed_at' => null,
                ]);
            }
        }

        if (! $dryRun) {
            $this->info('Imports reset to pending. Restart Horizon to process them.');
        }

        return self::SUCCESS;
    }
}
