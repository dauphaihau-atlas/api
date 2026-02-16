<?php

namespace App\Providers;

use App\Core\Application\Contracts\AuthServiceInterface;
use App\Core\Application\Contracts\UserCreatedNotifierInterface;
use App\Core\Application\Services\EmailServiceInterface;
use App\Infrastructure\Auth\SanctumAuthService;
use App\Infrastructure\External\Email\LogEmailService;
use App\Infrastructure\Notifications\LaravelUserCreatedNotifier;
use Illuminate\Support\ServiceProvider;

class UseCaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            EmailServiceInterface::class,
            LogEmailService::class
        );

        $this->app->bind(
            AuthServiceInterface::class,
            SanctumAuthService::class
        );

        $this->app->bind(
            UserCreatedNotifierInterface::class,
            LaravelUserCreatedNotifier::class
        );
    }

    public function boot(): void
    {
        //
    }
}
