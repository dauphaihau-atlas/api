<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Persistence\Eloquent\Models\ActivityLogModel;
use Illuminate\Console\Command;

class PruneActivityLogs extends Command
{
    protected $signature = 'app:prune-activity-logs
                            {--days=90 : Delete logs older than this many days}
                            {--dry-run : Preview what would be deleted without making changes}';

    protected $description = 'Delete activity log entries older than the given number of days';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $count = ActivityLogModel::query()
            ->where('created_at', '<', $cutoff)
            ->count();

        if ($count === 0) {
            $this->info('No activity logs to prune.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s %d activity log record(s) older than %d days.',
            $dryRun ? '[dry-run] Would delete' : 'Deleting',
            $count,
            $days,
        ));

        if (! $dryRun) {
            ActivityLogModel::query()
                ->where('created_at', '<', $cutoff)
                ->delete();
        }

        return self::SUCCESS;
    }
}
