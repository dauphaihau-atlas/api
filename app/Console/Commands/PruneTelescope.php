<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneTelescope extends Command
{
    protected $signature = 'app:prune-telescope
                            {--hours=48 : Delete entries older than this many hours}
                            {--dry-run : Preview what would be deleted without making changes}';

    protected $description = 'Delete old Telescope entries to prevent database bloat';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subHours($hours);

        $count = DB::table('telescope_entries')
            ->where('created_at', '<', $cutoff)
            ->count();

        if ($count === 0) {
            $this->info('No Telescope entries to prune.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s %d Telescope entr%s older than %d hour(s).',
            $dryRun ? '[dry-run] Would delete' : 'Deleting',
            $count,
            $count === 1 ? 'y' : 'ies',
            $hours,
        ));

        if (! $dryRun) {
            // Tags must be removed first to satisfy the foreign key constraint.
            DB::table('telescope_entries_tags')
                ->whereIn('entry_uuid', function ($sub): void {
                    $sub->select('uuid')
                        ->from('telescope_entries')
                        ->where('created_at', '<', $cutoff);
                })
                ->delete();

            DB::table('telescope_entries')
                ->where('created_at', '<', $cutoff)
                ->delete();
        }

        return self::SUCCESS;
    }
}
