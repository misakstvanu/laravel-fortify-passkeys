<?php

namespace Misakstvanu\LaravelFortifyPasskeys\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Misakstvanu\LaravelFortifyPasskeys\CredentialSourceRepository;
use Misakstvanu\LaravelFortifyPasskeys\Tests\Models\User;
use Misakstvanu\LaravelFortifyPasskeys\Tests\TestCase;
use Webauthn\CredentialRecord;
use Symfony\Component\Uid\Uuid;
use Webauthn\TrustPath\EmptyTrustPath;
use Webauthn\PublicKeyCredentialDescriptor;

class CredentialSourceRepositoryTest extends TestCase
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
    public function it_can_save_and_find_a_credential_source()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $repository = new CredentialSourceRepository();

        $credentialSource = CredentialRecord::create(
            'credential-id',
            PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            [],
            'attestation-type',
            new EmptyTrustPath(),
            Uuid::v4(),
            'public-key-data',
            (string)$user->id,
            0
        );

        $repository->saveCredentialSource($credentialSource);

        $found = $repository->findOneByCredentialId('credential-id');

        $this->assertNotNull($found);
        $this->assertEquals('credential-id', $found->publicKeyCredentialId);
        $this->assertEquals((string)$user->id, $found->userHandle);
    }
}
