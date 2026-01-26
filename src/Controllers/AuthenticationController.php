<?php

namespace Misakstvanu\LaravelFortifyPasskeys\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Misakstvanu\LaravelFortifyPasskeys\Services\PasskeyService;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

class AuthenticationController extends Controller
{
    public function __construct(
        protected PasskeyService $passkeyService
    ) {}

    public function options(Request $request): JsonResponse
    {
        $options = $this->passkeyService->generateOptions($request, null, false);
        return response()->json($options);
    }

    public function verify(Request $request): JsonResponse
    {
        $psr17Factory = new Psr17Factory();
        $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
        $serverRequest = $psrHttpFactory->createRequest($request);

        $result = $this->passkeyService->verify($request, $serverRequest);

        if ($result['verified'] && $result['user']) {
            Auth::login($result['user']);
            $request->session()->regenerate();
            return response()->json(['verified' => true]);
        }

        return response()->json(['status' => 'error', 'message' => 'Authentication failed'], 401);
    }
}
