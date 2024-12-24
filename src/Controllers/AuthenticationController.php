<?php

namespace Misakstvanu\LaravelFortifyPasskeys\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Misakstvanu\LaravelFortifyPasskeys\Requests\GenerateAuthenticationOptionsRequest;
use Misakstvanu\LaravelFortifyPasskeys\Services\PasskeyService;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;
use Throwable;
use Webauthn\Exception\InvalidDataException;

class AuthenticationController extends Controller {

    protected PasskeyService $passkeyService;

    public function __construct(PasskeyService $passkeyService)
    {
        $this->passkeyService = $passkeyService;
    }

    /**
     * @throws ValidationException
     * @throws RandomException
     * @throws RandomException
     */
    public function generateOptions(GenerateAuthenticationOptionsRequest $request): array {
        try {
            $user = config('passkeys.user_model')::where(config('passkeys.username_column'), $request->validated(config('passkeys.username_column')))->whereHas('passkeys')->firstOrFail();
        } catch (ModelNotFoundException $e) {
            throw new ModelNotFoundException('User not found', 404, $e);
        }

        return $this->passkeyService->generateOptions($request, $user);
    }

    /**
     * @throws InvalidDataException
     * @throws Throwable
     * @throws ValidationException
     */
    public function verify(Request $request, ServerRequestInterface $serverRequest): array
    {
        $response = $this->passkeyService->verify($request, $serverRequest);

        if ($response['verified']) {
            $user = config('passkeys.user_model')::where(config('passkeys.username_column'), $response['userHandle'])->firstOrFail();
            Auth::login($user);

            return ['verified' => true];
        }

        return ['verified' => false];
    }

}
