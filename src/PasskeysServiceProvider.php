<?php

namespace Misakstvanu\LaravelPasskeys;

use Illuminate\Support\ServiceProvider;

class PasskeysServiceProvider extends ServiceProvider {

    public function boot(): void {
        $this->loadRoutesFrom(__DIR__ . '/../routes/passkeys.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/../config/passkeys.php', 'passkeys');
    }

}
