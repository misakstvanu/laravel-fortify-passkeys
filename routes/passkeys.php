<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('passkeys.route_prefix'),
    'middleware' => ['web']
], function () {

    //fetch authentication options based on
    Route::post('login/options', [\Misakstvanu\LaravelPasskeys\Controllers\AuthenticationController::class, 'generateOptions']);

    Route::post('login', [\Misakstvanu\LaravelPasskeys\Controllers\AuthenticationController::class, 'verify']);


    Route::post('register/options', [\Misakstvanu\LaravelPasskeys\Controllers\RegistrationController::class, 'generateOptions']);

    Route::post('register', [\Misakstvanu\LaravelPasskeys\Controllers\RegistrationController::class, 'verify']);
});

