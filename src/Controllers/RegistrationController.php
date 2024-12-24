<?php

namespace Misakstvanu\LaravelFortifyPasskeys\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Misakstvanu\LaravelFortifyPasskeys\Services\PasskeyService;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;
use Throwable;
use Webauthn\Exception\InvalidDataException;

class RegistrationController extends Controller {

    protected PasskeyService $passkeyService;

    public function __construct(PasskeyService $passkeyService)
    {
        $this->passkeyService = $passkeyService;
    }

    /**
     * @throws RandomException
     */
    public function generateOptions(Request $request): array {
        return $this->passkeyService->generateOptions($request);
    }

    /**
     * @throws InvalidDataException
     * @throws Throwable
     * @throws ValidationException
     */
    public function verify(Request $request, ServerRequestInterface $serverRequest): array
    {
        $userData = $request->validate(config('passkeys.registration_user_validation'));

        $response = $this->passkeyService->verify($request, $serverRequest);

        if ($response['verified']) {
            $user = config('passkeys.user_model')::create(array_merge([
                config('passkeys.username_column') => $request->input(config('passkeys.username_column')),
            ], $userData));

            Auth::login($user);

            return ['verified' => true];
        }

        return ['verified' => false];
    }

}
