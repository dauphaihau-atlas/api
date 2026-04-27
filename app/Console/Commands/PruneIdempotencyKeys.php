<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Persistence\Eloquent\Models\IdempotencyKeyModel;
use Illuminate\Console\Command;

class PruneIdempotencyKeys extends Command
{
    protected $signature = 'app:prune-idempotency-keys
                            {--dry-run : Preview what would be deleted without making changes}';

    protected $description = 'Delete expired idempotency key records';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = IdempotencyKeyModel::query()->where('expires_at', '<', now());
        $count = $query->count();

        if ($count === 0) {
            $this->info('No expired idempotency keys to prune.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s %d expired idempotency key record(s).',
            $dryRun ? '[dry-run] Would delete' : 'Deleting',
            $count,
        ));

        if (! $dryRun) {
            $query->delete();
        }

        return self::SUCCESS;
    }
}
