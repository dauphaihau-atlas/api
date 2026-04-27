<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrunePasswordResets extends Command
{
    protected $signature = 'app:prune-password-resets
                            {--hours=24 : Delete tokens older than this many hours}
                            {--dry-run : Preview what would be deleted without making changes}';

    protected $description = 'Delete expired password reset tokens';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subHours($hours);

        $count = DB::table('password_reset_tokens')
            ->where('created_at', '<', $cutoff)
            ->count();

        if ($count === 0) {
            $this->info('No expired password reset tokens to prune.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s %d expired password reset token(s) older than %d hour(s).',
            $dryRun ? '[dry-run] Would delete' : 'Deleting',
            $count,
            $hours,
        ));

        if (! $dryRun) {
            DB::table('password_reset_tokens')
                ->where('created_at', '<', $cutoff)
                ->delete();
        }

        return self::SUCCESS;
    }
}
