<?php

namespace Misakstvanu\LaravelFortifyPasskeys;

use App\Models\User;
use Misakstvanu\LaravelFortifyPasskeys\Models\Passkey;
use Misakstvanu\LaravelFortifyPasskeys\Services\HasWebauthnSerializer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\Exception\InvalidDataException;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialUserEntity;

class CredentialSourceRepository {

    use HasWebauthnSerializer;

    public function findOneByCredentialId(string $publicKeyCredentialId): ?CredentialRecord {
        $authenticator = $this->findOneEntryByCredentialId($publicKeyCredentialId);

        if (!$authenticator) {
            return null;
        }

        return $this->getSerializer()->denormalize(
            $authenticator->public_key,
            CredentialRecord::class
        );
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
    public function saveCredentialSource(CredentialRecord $publicKeyCredentialSource): void {
        $user = config('passkeys.user_model')::where(
            config('passkeys.username_column'),
            $publicKeyCredentialSource->userHandle
        )->first();

        if (!$user) {
            // fallback to ID
            $user = config('passkeys.user_model')::findOrFail($publicKeyCredentialSource->userHandle);
        }

        $serializer = $this->getSerializer();
        // We need to use normalize here as SerializerInterface doesn't have it,
        // but the implementation usually does.
        /** @var \Symfony\Component\Serializer\Normalizer\NormalizerInterface $serializer */
        $publicKeyData = $serializer->normalize($publicKeyCredentialSource);

        if($passkey = $this->findOneEntryByCredentialId($publicKeyCredentialSource->publicKeyCredentialId)){
            $passkey->update([
                'public_key' => $publicKeyData,
            ]);
            return;
        }
        $user->passkeys()->save(new Passkey([
            'credential_id' => base64_encode($publicKeyCredentialSource->publicKeyCredentialId),
            'public_key' => $publicKeyData,
        ]));
    }

}
