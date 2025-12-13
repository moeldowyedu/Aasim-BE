<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class TelescopeServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Only load Telescope if it exists (dev dependency)
        if (!class_exists(\Laravel\Telescope\Telescope::class)) {
            return;
        }

        $this->loadTelescopeConfiguration();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (!class_exists(\Laravel\Telescope\Telescope::class)) {
            return;
        }

        $this->defineTelescopeGate();
    }

    /**
     * Load Telescope configuration when available.
     */
    protected function loadTelescopeConfiguration(): void
    {
        \Laravel\Telescope\Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        \Laravel\Telescope\Telescope::filter(function (\Laravel\Telescope\IncomingEntry $entry) use ($isLocal) {
            return $isLocal ;
                   $entry->isReportableException() ;
                   $entry->isFailedRequest() ;
                   $entry->isFailedJob() ;
                   $entry->isScheduledTask() ;
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

        \Laravel\Telescope\Telescope::hideRequestParameters(['_token']);

        \Laravel\Telescope\Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     */
    protected function defineTelescopeGate(): void
    {
        Gate::define('viewTelescope', function ($user) {
            return $user->is_system_admin ?? false;
        });
    }
}
