<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Application\UseCases\User\GetUserStats\GetUserStatsUseCase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WarmCache extends Command
{
    protected $signature = 'app:cache-warm';

    protected $description = 'Pre-populate Redis cache with user stats after a cold deploy or cache flush';

    public function handle(GetUserStatsUseCase $getUserStatsUseCase): int
    {
        $this->info('Warming cache...');

        Cache::tags(['users'])->flush();

        $getUserStatsUseCase->execute();

        $this->info('  users:stats:total_active   warmed');
        $this->info('  users:stats:total_deleted  warmed');
        $this->info('  users:stats:created_today  warmed');

        return self::SUCCESS;
    }
}
