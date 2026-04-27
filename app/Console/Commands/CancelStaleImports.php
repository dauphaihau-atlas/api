<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Domain\Enums\ImportStatus;
use App\Infrastructure\Persistence\Eloquent\Models\UserImportModel;
use Illuminate\Console\Command;

class CancelStaleImports extends Command
{
    protected $signature = 'app:cancel-stale-imports
                            {--minutes=120 : Mark imports stuck for longer than this many minutes as failed}
                            {--dry-run : Preview which imports would be cancelled without making changes}';

    protected $description = 'Mark pending or processing imports that have not progressed as failed';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subMinutes($minutes);

        $imports = UserImportModel::query()
            ->whereIn('status', [
                ImportStatus::Pending->value,
                ImportStatus::Processing->value,
            ])
            ->where('updated_at', '<', $cutoff)
            ->get();

        if ($imports->isEmpty()) {
            $this->info('No stale imports found.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s %d stale import(s) stuck for more than %d minute(s).',
            $dryRun ? '[dry-run] Would mark as failed' : 'Marking as failed',
            $imports->count(),
            $minutes,
        ));

        foreach ($imports as $import) {
            $this->line(sprintf(
                '  ID: %d | status: %s | last updated: %s',
                $import->id,
                $import->status->value,
                $import->updated_at,
            ));

            if (! $dryRun) {
                UserImportModel::where('id', $import->id)->update([
                    'status' => ImportStatus::Failed->value,
                    'completed_at' => now(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}
