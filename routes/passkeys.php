<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('passkeys.route_prefix'),
    'middleware' => config('passkeys.route_middleware')
], function () {
    Route::post('login/options', [\Misakstvanu\LaravelPasskeys\Controllers\AuthenticationController::class, 'generateOptions'])->name('passkeys.login.start');
    Route::post('login', [\Misakstvanu\LaravelPasskeys\Controllers\AuthenticationController::class, 'verify'])->name('passkeys.login.verify');

    Route::post('register/options', [\Misakstvanu\LaravelPasskeys\Controllers\RegistrationController::class, 'generateOptions'])->name('passkeys.register.start');
    Route::post('register', [\Misakstvanu\LaravelPasskeys\Controllers\RegistrationController::class, 'verify'])->name('passkeys.register.verify');
});

