<?php

namespace Misakstvanu\LaravelFortifyPasskeys\Controllers;

use App\Models\User;
use Cose\Algorithms;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Misakstvanu\LaravelFortifyPasskeys\CredentialSourceRepository;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;
use Throwable;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\AuthenticationExtensionsClientInputs;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

class RegistrationController extends Controller {

    // We use this key across several methods, so we're going to define it here
    const CREDENTIAL_CREATION_OPTIONS_SESSION_KEY = 'publicKeyCredentialCreationOptions';

    /**
     * @throws RandomException
     */
    public function generateOptions(Request $request): array {
        // Relying on Party Entity i.e. the application
        $rpEntity = PublicKeyCredentialRpEntity::create(
            config('app.name'),
            parse_url(config('app.url'), PHP_URL_HOST),
            null,
        );

        // User Entity
        $userEntity = PublicKeyCredentialUserEntity::create(
            $request->input(config('passkeys.username_column')),
            $request->input(config('passkeys.username_column')),
            $request->input(config('passkeys.username_column')),
            null,
        );

        // Challenge (random binary string)
        $challenge = random_bytes(16);

        // List of supported public key parameters
        $supportedPublicKeyParams = collect([
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

        // Instantiate PublicKeyCredentialCreationOptions object
        $pkCreationOptions =
            PublicKeyCredentialCreationOptions::create(
                $rpEntity,
                $userEntity,
                $challenge,
                $supportedPublicKeyParams,
                authenticatorSelection: AuthenticatorSelectionCriteria::create(),
                attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
                extensions: AuthenticationExtensionsClientInputs::createFromArray([
                    'credProps' => true,
                ])
            );

        $serializedOptions = $pkCreationOptions->jsonSerialize();

        if (!isset($serializedOptions['excludeCredentials'])) {
            // The JS side needs this, so let's set it up for success with an empty array
            $serializedOptions['excludeCredentials'] = [];
        }

        // This library for some reason doesn't serialize the extensions object,
        // so we'll do it manually
        $serializedOptions['extensions'] = $serializedOptions['extensions']->jsonSerialize();

        // Another thing we have to do manually for this to work the way we want to todo OR NOT??
        // $serializedOptions['authenticatorSelection']['residentKey'] = AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED;

        // It is important to store the user entity and the options object (e.g. in the session)
        // for the next step. The data will be needed to check the response from the device.
        $request->session()->flash(
            self::CREDENTIAL_CREATION_OPTIONS_SESSION_KEY,
            json_decode(json_encode($serializedOptions), true)
        );

        return $serializedOptions;
    }

    /**
     * @throws InvalidDataException
     * @throws Throwable
     * @throws ValidationException
     */
    public function verify(Request $request, ServerRequestInterface $serverRequest): array {
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

        // A loader that will load the response from the device
        $pkCredentialLoader = PublicKeyCredentialLoader::create(
            AttestationObjectLoader::create($attestationManager)
        );

        $publicKeyCredential = $pkCredentialLoader->load(json_encode($request->all()));

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
        $publicKeyCredentialSource = $responseValidator->check(
            $authenticatorAttestationResponse,
            PublicKeyCredentialCreationOptions::createFromArray(
                $request->session()->get(self::CREDENTIAL_CREATION_OPTIONS_SESSION_KEY)
            ),
            $serverRequest,
            config('passkeys.relying_party_ids')
        );

        // If we've gotten this far, the response is valid!

        // Save the public key credential source to the database
        $user = Auth::user();
        $publicKeyCredentialSource->userHandle = $user->{config('passkeys.username_column')};

        $pkSourceRepo->saveCredentialSource($publicKeyCredentialSource);

        return [
            'verified' => true,
        ];
    }

    /**
     * @throws InvalidDataException
     * @throws Throwable
     * @throws ValidationException
     */
    public function registerNewUser(Request $request, ServerRequestInterface $serverRequest): array {
        $userData = $request->validate(config('passkeys.registration_user_validation'));

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

        // A loader that will load the response from the device
        $pkCredentialLoader = PublicKeyCredentialLoader::create(
            AttestationObjectLoader::create($attestationManager)
        );

        $publicKeyCredential = $pkCredentialLoader->load(json_encode($request->all()));

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
        $publicKeyCredentialSource = $responseValidator->check(
            $authenticatorAttestationResponse,
            PublicKeyCredentialCreationOptions::createFromArray(
                $request->session()->get(self::CREDENTIAL_CREATION_OPTIONS_SESSION_KEY)
            ),
            $serverRequest,
            config('passkeys.relying_party_ids')
        );

        // If we've gotten this far, the response is valid!

        // Save the user and the public key credential source to the database
        $user = config('passkeys.user_model')::create(array_merge([
            config('passkeys.username_column') => $publicKeyCredentialSource->userHandle,
        ], $userData));

        $pkSourceRepo->saveCredentialSource($publicKeyCredentialSource);

        Auth::login($user);

        return [
            'verified' => true,
        ];
    }

}
