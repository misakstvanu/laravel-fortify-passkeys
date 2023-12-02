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
    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource {
        $authenticator = $this->findOneEntryByCredentialId($publicKeyCredentialId);

        if (!$authenticator) {
            return null;
        }

        return PublicKeyCredentialSource::createFromArray($authenticator->public_key);
    }

    protected function findOneEntryByCredentialId(string $publicKeyCredentialId): ?Passkey {
        return Passkey::where(
            'credential_id',
            base64_encode($publicKeyCredentialId)
        )->first();
    }

    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array {
        return config('passkeys.user_model')::with('passkeys')
            ->where('id', $publicKeyCredentialUserEntity->id)
            ->first()
            ->passkeys
            ->toArray();
    }

    /**
     * @throws InvalidDataException
     */
    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void {
        $user = config('passkeys.user_model')::where(
            config('passkeys.username_column'),
            $publicKeyCredentialSource->userHandle
        )->firstOrFail();

        if($passkey = $this->findOneEntryByCredentialId($publicKeyCredentialSource->publicKeyCredentialId)){
            $passkey->update([
                'public_key' => $publicKeyCredentialSource->jsonSerialize(),
            ]);
            return;
        }
        $user->passkeys()->save(new Passkey([
            'credential_id' => $publicKeyCredentialSource->publicKeyCredentialId,
            'public_key' => $publicKeyCredentialSource->jsonSerialize(),
        ]));
    }

}
