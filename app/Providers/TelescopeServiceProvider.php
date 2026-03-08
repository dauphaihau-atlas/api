<?php

namespace App\Providers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        // Allow unauthenticated access in local; otherwise require admin or explicit email list in gate()
        Telescope::auth(function ($request) {
            if ($this->app->environment('local')) {
                return true;
            }
            $user = $request->user();

            return $user !== null && Gate::check('viewTelescope', [$user]);
        });

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
              $entry->isReportableException() ||
              $entry->isFailedRequest() ||
              $entry->isFailedJob() ||
              $entry->isScheduledTask() ||
              $entry->hasMonitoredTag();
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders(['cookie', 'x-csrf-token', 'x-xsrf-token']);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function (UserModel $user) {
            // Allow admin role (matches AppServiceProvider admin gate)
            if (($user->role ?? null) === 'admin') {
                return true;
            }

            return in_array($user->email, [
                //
            ]);
        });
    }
}
