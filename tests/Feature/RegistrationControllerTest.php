<?php

namespace Misakstvanu\LaravelFortifyPasskeys\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Misakstvanu\LaravelFortifyPasskeys\Tests\TestCase;

class RegistrationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    /** @test */
    public function it_can_get_registration_options()
    {
        $response = $this->postJson(route('passkeys.register.options'), [
            'email' => 'test@example.com'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'rp', 'user', 'challenge', 'pubKeyCredParams'
        ]);
    }
}
