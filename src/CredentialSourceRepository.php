<?php

namespace Misakstvanu\LaravelPasskeys;

use App\Models\User;
use Misakstvanu\LaravelPasskeys\Models\Passkey;
use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;

class CredentialSourceRepository implements PublicKeyCredentialSourceRepository {

    /**
     * @throws InvalidDataException
     */
    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $authenticator = Passkey::where(
            'credential_id',
            base64_encode($publicKeyCredentialId)
        )->first();

        if (!$authenticator) {
            return null;
        }

        return PublicKeyCredentialSource::createFromArray($authenticator->public_key);
    }

    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        return User::with('passkeys')
            ->where('id', $publicKeyCredentialUserEntity->getId())
            ->first()
            ->passkeys
            ->toArray();
    }

    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        $user = User::where(
            'username',
            $publicKeyCredentialSource->userHandle
        )->firstOrFail();

        $user->authenticators()->save(new Passkey([
            'credential_id' => $publicKeyCredentialSource->getPublicKeyCredentialId(),
            'public_key'    => $publicKeyCredentialSource->jsonSerialize(),
        ]));
    }

}
