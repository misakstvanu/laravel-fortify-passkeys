<?php /** @noinspection DuplicatedCode */

namespace Misakstvanu\LaravelFortifyPasskeys\Services;

use Cose\Algorithms;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Misakstvanu\LaravelFortifyPasskeys\CredentialSourceRepository;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Throwable;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\AuthenticationExtensions;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CollectedClientData;
use Webauthn\Exception\AuthenticationExtensionException;
use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;


class PasskeyService
{

    const CREDENTIAL_CREATION_OPTIONS_SESSION_KEY = 'publicKeyCredentialCreationOptions';

    private function getSerializer(): Serializer
    {
        return new Serializer(
            [new ObjectNormalizer()],
            [new JsonEncoder()]
        );
    }

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

        // User Entity
        if ($user) {
            $userEntity = PublicKeyCredentialUserEntity::create(
                $user->email,
                (string)$user->id,
                $user->name,
            );
        } else {
            $userEntity = PublicKeyCredentialUserEntity::create(
                $request->input(config('passkeys.username_column')),
                $request->input(config('passkeys.username_column')),
                $request->input(config('passkeys.username_column')),
            );
        }

        // Challenge (random binary string)
        $challenge = random_bytes(16);

        // Instantiate PublicKeyCredentialCreationOptions object
        $pkCreationOptions =
            PublicKeyCredentialCreationOptions::create(
                $rpEntity,
                $userEntity,
                $challenge,
                $this->supportedPublicKeyAlgorithms(),
                authenticatorSelection: AuthenticatorSelectionCriteria::create(),
                attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
                extensions: $isRegistration ? new AuthenticationExtensions([
                    'credProps' => true,
                ]) : null,
            );

        // Instantiate your Symfony serializer
        $serializer = $this->getSerializer();

        // Convert the $pkCreationOptions object into an array
        $serializedOptions = $serializer->normalize($pkCreationOptions);

        // If you need the final structure to be JSON, you can do:
        // $json = $serializer->serialize($pkCreationOptions, 'json');
        // but typically for session storage, an array is just fine.
        $serializedOptions['challenge'] = base64_encode($serializedOptions['challenge']);
        if (!isset($serializedOptions['excludeCredentials'])) {
            // The JS side needs this, so let's set it up for success with an empty array
            $serializedOptions['excludeCredentials'] = [];
        }

        // If registration, and you need to ensure 'extensions' is present:
        if ($isRegistration && !isset($serializedOptions['extensions'])) {
            // The library might omit it, so ensure it's an empty array or something you need:
            $serializedOptions['extensions'] = ['credProps' => true];
        }

        // Now just flash the array into the session
        $request->session()->flash(
            self::CREDENTIAL_CREATION_OPTIONS_SESSION_KEY,
            serialize($pkCreationOptions)
        );

        return $serializedOptions;  // This is already an array, suitable for your frontend
    }

    /**
     * @throws InvalidDataException
     * @throws Throwable
     * @throws ValidationException
     */
    public function verify(Request $request, ServerRequestInterface $serverRequest, $user = null): array
    {
        // A repo of our public key credentials
        $pkSourceRepo = new CredentialSourceRepository();

        $attestationManager = AttestationStatementSupportManager::create();
        $attestationManager->add(NoneAttestationStatementSupport::create());

        // The validator that will check the response from the device
        $responseValidator = AuthenticatorAttestationResponseValidator::create(
            $attestationManager,
            $pkSourceRepo,
            null,
            ExtensionOutputCheckerHandler::create()
        );

        $publicKeyCredential = new PublicKeyCredential(
            $request->input('id'),
            $request->input('type'),
            $request->input('rawId'),
            AuthenticatorAttestationResponse::create(
                CollectedClientData::createFormJson($request->input('response.clientDataJSON')),
                AttestationObjectLoader::create($attestationManager)->load($request->input('response.attestationObject'))
            )
        );

        $authenticatorAttestationResponse = $publicKeyCredential->response;

        if (!$authenticatorAttestationResponse instanceof AuthenticatorAttestationResponse) {
            throw ValidationException::withMessages([
                config('passkeys.username_column') => 'Invalid response type',
            ]);
        }


        // Check the response from the device, this will
        // throw an exception if the response is invalid.
        // For the purposes of this demo, we are letting
        // the exception bubble up so we can see what is
        // going on.
        $serializedArray = $request->session()->get(self::CREDENTIAL_CREATION_OPTIONS_SESSION_KEY);
        $credentialCreationOptions = unserialize($serializedArray);
        $publicKeyCredentialSource = $responseValidator->check(
            $authenticatorAttestationResponse,
            $credentialCreationOptions,
            $serverRequest,
            config('passkeys.relying_party_ids')
        );

        // If we've gotten this far, the response is valid!

        // Save the public key credential source to the database
        if ($user) {
            $publicKeyCredentialSource->userHandle = $user->{config('passkeys.username_column')};
        } else {
            $publicKeyCredentialSource->userHandle = $request->input(config('passkeys.username_column'));
        }

        $pkSourceRepo->saveCredentialSource($publicKeyCredentialSource);

        return [
            'verified' => true,
        ];
    }

}
