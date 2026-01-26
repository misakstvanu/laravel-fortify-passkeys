<?php

namespace Misakstvanu\LaravelFortifyPasskeys\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Misakstvanu\LaravelFortifyPasskeys\Services\PasskeyService;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

class AddPasskeyController extends Controller
{
    public function __construct(
        protected PasskeyService $passkeyService
    ) {}

    public function options(Request $request): JsonResponse
    {
        $options = $this->passkeyService->generateOptions($request, $request->user(), true);
        return response()->json($options);
    }

    public function add(Request $request): JsonResponse
    {
        $psr17Factory = new Psr17Factory();
        $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
        $serverRequest = $psrHttpFactory->createRequest($request);

        $result = $this->passkeyService->verify($request, $serverRequest, $request->user());

        if ($result['verified']) {
            return response()->json(['status' => 'success']);
        }

        return response()->json(['status' => 'error', 'message' => 'Failed to add passkey'], 400);
    }
}
