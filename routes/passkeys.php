<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('fortify.prefix'),
    'middleware' => config('passkeys.route_middleware')
], function () {
    Route::post('passkey/login/options', [\Misakstvanu\LaravelFortifyPasskeys\Controllers\AuthenticationController::class, 'generateOptions'])->name('passkeys.login.start');
    Route::post('passkey/login', [\Misakstvanu\LaravelFortifyPasskeys\Controllers\AuthenticationController::class, 'verify'])->name('passkeys.login.verify');

    Route::post('passkey/register/options', [\Misakstvanu\LaravelFortifyPasskeys\Controllers\RegistrationController::class, 'generateOptions'])->name('passkeys.register.start');
    Route::post('passkey/register', [\Misakstvanu\LaravelFortifyPasskeys\Controllers\RegistrationController::class, 'verify'])->name('passkeys.register.verify');
});

