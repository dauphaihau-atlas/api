<?php

namespace App\Providers;

use App\Infrastructure\Persistence\Eloquent\Models\ActivityLogModel;
use App\Infrastructure\Persistence\Eloquent\Models\TenantModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Observers\UserActivityLogObserver;
use App\Infrastructure\Persistence\Eloquent\Observers\UserCacheObserver;
use App\Presentation\Http\Policies\ActivityLogPolicy;
use App\Presentation\Http\Policies\TenantPolicy;
use App\Presentation\Http\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('heavy', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        Gate::define('admin', function ($user) {
            return $user !== null && (
                $user->hasRole('tenant_owner')
                || $user->hasRole('admin')
                || $user->hasRole('super_admin')
            );
        });

        Gate::define('super_admin', function ($user) {
            return $user !== null && $user->hasRole('super_admin');
        });

        Gate::policy(UserModel::class, UserPolicy::class);
        Gate::policy(ActivityLogModel::class, ActivityLogPolicy::class);
        Gate::policy(TenantModel::class, TenantPolicy::class);

        UserModel::observe(UserCacheObserver::class);
        UserModel::observe(UserActivityLogObserver::class);
    }
}
