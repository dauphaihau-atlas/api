<?php

namespace App\Providers;

use App\Core\Application\Contracts\AuthServiceInterface;
use App\Core\Application\Services\EmailServiceInterface;
use App\Infrastructure\Auth\SanctumAuthService;
use App\Infrastructure\External\Email\LogEmailService;
use App\Infrastructure\Tenant\TenantContext;
use Illuminate\Support\ServiceProvider;

class UseCaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, fn () => new TenantContext);

        $this->app->bind(
            EmailServiceInterface::class,
            LogEmailService::class
        );

        $this->app->bind(
            AuthServiceInterface::class,
            SanctumAuthService::class
        );
    }

    public function boot(): void
    {
        //
    }
}
