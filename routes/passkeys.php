<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Misakstvanu\LaravelFortifyPasskeys\Controllers\AuthenticationController;
use Misakstvanu\LaravelFortifyPasskeys\Controllers\RegistrationController;
use Misakstvanu\LaravelFortifyPasskeys\Controllers\AddPasskeyController;

Route::group([
    'prefix' => config('passkeys.route_prefix'),
    'middleware' => config('passkeys.route_middleware')
], function () {
    Route::post('passkey/login/options', [AuthenticationController::class, 'options'])->name('passkeys.login.options');
    Route::post('passkey/login', [AuthenticationController::class, 'verify'])->name('passkeys.login.verify');

    // Registration...
    if (Features::enabled(Features::registration())) {
        Route::post('passkey/register/options', [RegistrationController::class, 'options'])->name('passkeys.register.options');
        Route::post('passkey/register', [RegistrationController::class, 'register'])->name('passkeys.register.verify');
    }

    // Add Passkey...
    Route::post('passkey/add/options', [AddPasskeyController::class, 'options'])->name('passkeys.add.options')->middleware('auth');
    Route::post('passkey/add', [AddPasskeyController::class, 'add'])->name('passkeys.add.verify')->middleware('auth');
});
