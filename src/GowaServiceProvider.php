<?php

declare(strict_types=1);

namespace Gowa\Laravel;

use Gowa\Laravel\Notifications\GowaChannel;
use Gowa\Laravel\Webhook\GowaWebhookController;
use Gowa\Sdk\Config;
use Gowa\Sdk\GowaClient;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class GowaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/gowa.php', 'gowa');

        $this->app->singleton(GowaClient::class, function () {
            return new GowaClient(new Config(
                baseUrl: (string) config('gowa.base_url', ''),
                username: (string) config('gowa.username', ''),
                password: (string) config('gowa.password', ''),
                timeout: (int) config('gowa.timeout', 15),
            ));
        });

        $this->app->singleton(GowaChannel::class, function () {
            return new GowaChannel($this->app->make(GowaClient::class));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/gowa.php' => config_path('gowa.php'),
            ], 'gowa-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'gowa-migrations');
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerWebhookRoute();
    }

    private function registerWebhookRoute(): void
    {
        $path = config('gowa.webhook.path');

        if (empty($path)) {
            return;
        }

        Route::post("{$path}/{deviceId}", GowaWebhookController::class)
            ->name('gowa.webhook');
    }
}
