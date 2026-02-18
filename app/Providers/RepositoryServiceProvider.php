<?php

namespace App\Providers;

use App\Core\Application\Contracts\ActivityLogRepositoryInterface;
use App\Core\Application\Contracts\UserImportRepositoryInterface;
use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentActivityLogRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentUserImportRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            UserRepositoryInterface::class,
            EloquentUserRepository::class
        );

        $this->app->bind(
            UserImportRepositoryInterface::class,
            EloquentUserImportRepository::class
        );

        $this->app->bind(
            ActivityLogRepositoryInterface::class,
            EloquentActivityLogRepository::class
        );
    }

    public function boot(): void
    {
        //
    }
}
