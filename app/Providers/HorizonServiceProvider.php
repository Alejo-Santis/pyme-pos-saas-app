<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * Monitoreo de colas es un asunto del SaaS (landlord), no de un tenant
     * puntual — por eso se valida contra el guard 'admin' (AdminUser), no
     * contra el guard 'web' de un tenant.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function () {
            return \Illuminate\Support\Facades\Auth::guard('admin')->check();
        });
    }
}
