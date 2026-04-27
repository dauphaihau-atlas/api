<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Domain\Enums\ImportStatus;
use App\Infrastructure\Persistence\Eloquent\Models\UserImportModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneImports extends Command
{
    protected $signature = 'app:prune-imports
                            {--days=30 : Delete imports older than this many days}
                            {--dry-run : Preview what would be deleted without making changes}';

    protected $description = 'Delete completed, failed, and cancelled import records and their CSV files';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $imports = UserImportModel::query()
            ->whereIn('status', [
                ImportStatus::Completed->value,
                ImportStatus::Failed->value,
                ImportStatus::Cancelled->value,
            ])
            ->where('created_at', '<', $cutoff)
            ->get();

        if ($imports->isEmpty()) {
            $this->info('No imports to prune.');

            return self::SUCCESS;
        }

        $disk = Storage::disk(config('filesystems.imports_disk', 'local'));
        $deletedFiles = 0;
        $missingFiles = 0;

        $this->info(sprintf(
            '%s %d import record(s) older than %d days.',
            $dryRun ? '[dry-run] Would delete' : 'Deleting',
            $imports->count(),
            $days,
        ));

        foreach ($imports as $import) {
            $path = $import->file_path;

            if ($path !== null && $path !== '') {
                if ($disk->exists($path)) {
                    if (! $dryRun) {
                        $disk->delete($path);
                    }
                    $deletedFiles++;
                } else {
                    $missingFiles++;
                }
            }

            if (! $dryRun) {
                $import->delete();
            }
        }

        $this->line(sprintf('  Files deleted  : %d', $deletedFiles));
        $this->line(sprintf('  Files missing  : %d', $missingFiles));
        $this->line(sprintf('  Records pruned : %d', $imports->count()));

        return self::SUCCESS;
    }
}
