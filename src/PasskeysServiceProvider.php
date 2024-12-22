<?php

namespace Misakstvanu\LaravelFortifyPasskeys;

use Illuminate\Support\ServiceProvider;

class PasskeysServiceProvider extends ServiceProvider {

    public function boot(): void {
        $this->publishes([
            __DIR__ . '/../config/passkeys.php' => config_path('passkeys.php'),
        ], 'laravel-fortify-passkeys-config');
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'laravel-fortify-passkeys-migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes/passkeys.php');
    }

    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/../config/passkeys.php', 'passkeys');
    }

}
