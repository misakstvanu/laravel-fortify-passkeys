<?php

namespace Misakstvanu\LaravelFortifyPasskeys\Services;

use Cose\Algorithms;
use Illuminate\Http\Request;
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
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialParameters;

class PasskeyService {

    const CREDENTIAL_CREATION_OPTIONS_SESSION_KEY = 'publicKeyCredentialCreationOptions';
    const CREDENTIAL_REQUEST_OPTIONS_SESSION_KEY = 'publicKeyCredentialRequestOptions';

    /**
     * @throws RandomException
     */
    public function generateOptions(Request $request, $user = null, $isRegistration = false): array {
        // Relying on Party Entity i.e. the application
        $rpEntity = PublicKeyCredentialRpEntity::create(
            config('app.name'),
            parse_url(config('app.url'), PHP_URL_HOST),
            null,
        );

        // User Entity
        if ($user) {
            $userEntity = PublicKeyCredentialUserEntity::create(
                $user->email,
                (string) $user->id,
                $user->name,
                null,
            );
        } else {
            $userEntity = PublicKeyCredentialUserEntity::create(
                $request->input(config('passkeys.username_column')),
                $request->input(config('passkeys.username_column')),
                $request->input(config('passkeys.username_column')),
                null,
            );
        }

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
                extensions: $isRegistration ? AuthenticationExtensionsClientInputs::createFromArray([
                    'credProps' => true,
                ]) : null
            );

        $serializedOptions = $pkCreationOptions->jsonSerialize();

        if (!isset($serializedOptions['excludeCredentials'])) {
            // The JS side needs this, so let's set it up for success with an empty array
            $serializedOptions['excludeCredentials'] = [];
        }

        // This library for some reason doesn't serialize the extensions object,
        // so we'll do it manually
        if ($isRegistration) {
            $serializedOptions['extensions'] = $serializedOptions['extensions']->jsonSerialize();
        }

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
    public function verify(Request $request, ServerRequestInterface $serverRequest, $user = null): array {
        // A repo of our public key credentials
        $pkSourceRepo = new CredentialSourceRepository();

        $attestationManager = AttestationStatementSupportManager::create();
        $attestationManager->add(NoneAttestationStatementSupport::create());

        // The validator that will check the response from the device
        $attestationResponseValidator = AuthenticatorAttestationResponseValidator::create(
            $attestationManager,
            $pkSourceRepo,
            null,
            ExtensionOutputCheckerHandler::create()
        );

        $assertionResponseValidator = AuthenticatorAssertionResponseValidator::create(
            $pkSourceRepo,
            null,
            ExtensionOutputCheckerHandler::create()
        );

        // A loader that will load the response from the device
        $pkCredentialLoader = PublicKeyCredentialLoader::create(
            AttestationObjectLoader::create($attestationManager)
        );

        $publicKeyCredential = $pkCredentialLoader->load(json_encode($request->all()));

        $authenticatorResponse = $publicKeyCredential->response;

        if ($authenticatorResponse instanceof AuthenticatorAttestationResponse) {
            $publicKeyCredentialSource = $attestationResponseValidator->check(
                $authenticatorResponse,
                PublicKeyCredentialCreationOptions::createFromArray(
                    $request->session()->get(self::CREDENTIAL_CREATION_OPTIONS_SESSION_KEY)
                ),
                $serverRequest,
                config('passkeys.relying_party_ids')
            );
        } elseif ($authenticatorResponse instanceof AuthenticatorAssertionResponse) {
            $publicKeyCredentialSource = $assertionResponseValidator->check(
                $authenticatorResponse,
                PublicKeyCredentialRequestOptions::createFromArray(
                    $request->session()->get(self::CREDENTIAL_REQUEST_OPTIONS_SESSION_KEY)
                ),
                $serverRequest,
                config('passkeys.relying_party_ids')
            );
        } else {
            throw ValidationException::withMessages([
                'error' => 'Invalid response type',
            ]);
        }

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
