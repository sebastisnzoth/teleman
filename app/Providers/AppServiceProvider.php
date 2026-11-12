<?php

namespace App\Providers;

use App\Models\UserKycVerification;
use App\Policies\KycPolicy;
use App\Services\Telephony\TelephonyLock;
use App\Services\Telephony\TelephonyManager;
use App\Services\TwilioService;
use App\Services\TwiMLService;
use App\Services\WebhookHandlerService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register Twilio services as singletons
        $this->app->singleton(TwilioService::class, function ($app) {
            return new TwilioService;
        });

        $this->app->singleton(TwiMLService::class, function ($app) {
            return new TwiMLService;
        });

        $this->app->singleton(WebhookHandlerService::class, function ($app) {
            return new WebhookHandlerService;
        });

        // Telephony provider lock + manager — see App\Services\Telephony.
        $this->app->singleton(TelephonyLock::class, function ($app) {
            return new TelephonyLock;
        });

        $this->app->singleton(TelephonyManager::class, function ($app) {
            return new TelephonyManager($app->make(TelephonyLock::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Register policies
        Gate::policy(UserKycVerification::class, KycPolicy::class);
    }
}
