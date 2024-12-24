<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Misakstvanu\LaravelFortifyPasskeys\Controllers\AuthenticationController;
use Misakstvanu\LaravelFortifyPasskeys\Controllers\RegistrationController;

Route::group([
    'prefix' => config('passkeys.route_prefix'),
    'middleware' => config('passkeys.route_middleware')
], function () {
    Route::post('passkey/login/options', [AuthenticationController::class, 'generateOptions'])->name('passkeys.login.start');
    Route::post('passkey/login', [AuthenticationController::class, 'verify'])->name('passkeys.login.verify');

    // Registration...
    if (Features::enabled(Features::registration())) {
        Route::post('passkey/register/options', [RegistrationController::class, 'generateOptions'])->name('passkeys.register.start');
        Route::post('passkey/register', [RegistrationController::class, 'verify'])->name('passkeys.register.verify');
    }
});

