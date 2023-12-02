<?php

namespace Misakstvanu\LaravelPasskeys;

use Illuminate\Support\ServiceProvider;

class PasskeysServiceProvider extends ServiceProvider {

    public function boot(): void {
        $this->publishes([
            __DIR__ . '/../config/passkeys.php' => config_path('passkeys.php'),
        ], 'laravel-passkeys-config');
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'laravel-passkeys-migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes/passkeys.php');
    }

    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/../config/passkeys.php', 'passkeys');
    }

}
