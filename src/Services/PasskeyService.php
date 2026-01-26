<?php /** @noinspection DuplicatedCode */

namespace Misakstvanu\LaravelFortifyPasskeys\Services;

use Cose\Algorithms;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Misakstvanu\LaravelFortifyPasskeys\CredentialSourceRepository;
use Misakstvanu\LaravelFortifyPasskeys\Services\HasWebauthnSerializer;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Throwable;
use Webauthn\AuthenticationExtensions\AuthenticationExtensions;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManager;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CollectedClientData;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\Exception\AuthenticationExtensionException;
use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;


class PasskeyService
{
    use HasWebauthnSerializer;

    const CREDENTIAL_OPTIONS_SESSION_KEY = 'publicKeyCredentialOptions';

    protected function supportedPublicKeyAlgorithms(): array
    {
        return collect([
            Algorithms::COSE_ALGORITHM_ES256,
            Algorithms::COSE_ALGORITHM_ES256K,
            Algorithms::COSE_ALGORITHM_ES384,
            Algorithms::COSE_ALGORITHM_ES512,
            Algorithms::COSE_ALGORITHM_RS256,
            Algorithms::COSE_ALGORITHM_RS384,
            Algorithms::COSE_ALGORITHM_RS512,
            Algorithms::COSE_ALGORITHM_PS256,
            Algorithms::COSE_ALGORITHM_PS384,
            Algorithms::COSE_ALGORITHM_PS512,
            Algorithms::COSE_ALGORITHM_EDDSA,
            Algorithms::COSE_ALGORITHM_ED256,
            Algorithms::COSE_ALGORITHM_ED512,
        ])->map(
            fn($algorithm) => PublicKeyCredentialParameters::create('public-key', $algorithm)
        )->toArray();
    }

    /**
     * @throws RandomException
     * @throws AuthenticationExtensionException
     * @throws ExceptionInterface
     */
    public function generateOptions(Request $request, $user = null, $isRegistration = false): array
    {
        // Relying on Party Entity i.e. the application
        $rpEntity = PublicKeyCredentialRpEntity::create(
            config('app.name'),
            parse_url(config('app.url'), PHP_URL_HOST),
        );

        // Challenge (random binary string)
        $challenge = random_bytes(32);

        if ($isRegistration) {
            // User Entity
            if ($user) {
                $userEntity = PublicKeyCredentialUserEntity::create(
                    $user->email,
                    (string)$user->id,
                    $user->name,
                );
            } else {
                $username = $request->input(config('passkeys.username_column'));
                $userEntity = PublicKeyCredentialUserEntity::create(
                    $username,
                    $username, // Use username as ID if user doesn't exist yet (for registration)
                    $username,
                );
            }

            $options = PublicKeyCredentialCreationOptions::create(
                $rpEntity,
                $userEntity,
                $challenge,
                $this->supportedPublicKeyAlgorithms(),
                authenticatorSelection: AuthenticatorSelectionCriteria::create(),
                attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
                extensions: new AuthenticationExtensions([
                    'credProps' => true,
                ]),
            );
        } else {
            $options = PublicKeyCredentialRequestOptions::create(
                $challenge,
                rpId: $rpEntity->id,
                userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            );
        }

        // Instantiate your Symfony serializer
        $serializer = $this->getSerializer();

        // Now just flash the object into the session
        // Laravel will handle serialization of the object.
        $request->session()->put(
            self::CREDENTIAL_OPTIONS_SESSION_KEY,
            $options
        );

        return $serializer->normalize($options);
    }

    /**
     * @throws InvalidDataException
     * @throws Throwable
     * @throws ValidationException
     */
    public function verify(Request $request, ServerRequestInterface $serverRequest, $user = null): array
    {
        $options = $request->session()->get(self::CREDENTIAL_OPTIONS_SESSION_KEY);

        if (!$options) {
            throw ValidationException::withMessages([
                config('passkeys.username_column') => 'Session expired',
            ]);
        }

        $pkSourceRepo = new CredentialSourceRepository();

        $publicKeyCredential = $this->getSerializer()->deserialize(
            $request->getContent(),
            PublicKeyCredential::class,
            'json'
        );

        $authenticatorResponse = $publicKeyCredential->response;

        if ($authenticatorResponse instanceof AuthenticatorAssertionResponse) {
            $factory = new CeremonyStepManagerFactory();
            $factory->setAlgorithmManager($this->getAlgorithmManager());
            $factory->setAllowedOrigins(config('passkeys.relying_party_ids'));

            $validator = AuthenticatorAssertionResponseValidator::create(
                $factory->requestCeremony(),
            );

            $credential = $pkSourceRepo->findOneByCredentialId($publicKeyCredential->rawId);
            if ($credential === null) {
                throw ValidationException::withMessages([
                    config('passkeys.username_column') => 'Invalid credential',
                ]);
            }

            $publicKeyCredentialSource = $validator->check(
                $credential,
                $authenticatorResponse,
                $options,
                $request->getHost(),
                $authenticatorResponse->userHandle
            );
        } elseif ($authenticatorResponse instanceof AuthenticatorAttestationResponse) {
            $factory = new CeremonyStepManagerFactory();
            $factory->setAlgorithmManager($this->getAlgorithmManager());
            $factory->setAttestationStatementSupportManager($this->getAttestationStatementSupportManager());
            $factory->setAllowedOrigins(config('passkeys.relying_party_ids'));

            $validator = AuthenticatorAttestationResponseValidator::create(
                $factory->creationCeremony(),
            );

            $publicKeyCredentialSource = $validator->check(
                $authenticatorResponse,
                $options,
                $request->getHost()
            );
        } else {
            throw ValidationException::withMessages([
                config('passkeys.username_column') => 'Invalid response type',
            ]);
        }

        // If we've gotten this far, the response is valid!

        // Save the public key credential source to the database
        if ($user) {
            $publicKeyCredentialSource->userHandle = (string)$user->id;
        } else {
            // For login, we might need to find the user from the credential source
            $user = config('passkeys.user_model')::where('id', $publicKeyCredentialSource->userHandle)->first();
            if (!$user) {
                // fallback to username column if handle is not ID
                $user = config('passkeys.user_model')::where(config('passkeys.username_column'), $publicKeyCredentialSource->userHandle)->first();
            }
        }

        if ($user) {
            $publicKeyCredentialSource->userHandle = (string)$user->id;
            $pkSourceRepo->saveCredentialSource($publicKeyCredentialSource);
        }

        return [
            'verified' => true,
            'user' => $user,
        ];
    }
}
