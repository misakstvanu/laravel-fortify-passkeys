<?php

namespace Misakstvanu\LaravelFortifyPasskeys\Tests;

use Misakstvanu\LaravelFortifyPasskeys\PasskeysServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            PasskeysServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('passkeys.user_model', \Misakstvanu\LaravelFortifyPasskeys\Tests\Models\User::class);
        config()->set('fortify.features', [
            \Laravel\Fortify\Features::registration(),
        ]);
    }
}
