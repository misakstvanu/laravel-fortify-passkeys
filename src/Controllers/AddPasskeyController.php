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
use Webauthn\PublicKeyCredentialRequestOptions;

class AddPasskeyController extends Controller {

    protected PasskeyService $passkeyService;

    public function __construct(PasskeyService $passkeyService)
    {
        $this->passkeyService = $passkeyService;
    }

    /**
     * @throws RandomException
     */
    public function generateOptions(Request $request): array {
        $user = Auth::user();
        return $this->passkeyService->generateOptions($request, $user);
    }

    /**
     * @throws InvalidDataException
     * @throws Throwable
     * @throws ValidationException
     */
    public function verify(Request $request, ServerRequestInterface $serverRequest): array
    {
        $user = Auth::user();
        $publicKeyCredentialSource = $this->passkeyService->verify($request, $serverRequest, $user);

        return $publicKeyCredentialSource;
    }

}
