<?php

namespace Misakstvanu\LaravelFortifyPasskeys\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Misakstvanu\LaravelFortifyPasskeys\Services\PasskeyService;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

class RegistrationController extends Controller
{
    public function __construct(
        protected PasskeyService $passkeyService
    ) {}

    public function options(Request $request): JsonResponse
    {
        $options = $this->passkeyService->generateOptions($request, null, true);
        return response()->json($options);
    }

    public function register(Request $request): JsonResponse
    {
        $psr17Factory = new Psr17Factory();
        $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
        $serverRequest = $psrHttpFactory->createRequest($request);

        // Find or create user if needed.
        // For simplicity, we expect the user to either exist or we create one.
        $username = $request->input(config('passkeys.username_column'));
        $userModel = config('passkeys.user_model');
        $user = $userModel::where(config('passkeys.username_column'), $username)->first();

        if (!$user) {
            // Check validation
            $request->validate([
                config('passkeys.username_column') => config('passkeys.username_column_validation'),
            ]);

            $user = $userModel::create([
                config('passkeys.username_column') => $username,
                'name' => $username, // default name
                'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)),
            ]);
        }

        $result = $this->passkeyService->verify($request, $serverRequest, $user);

        if ($result['verified']) {
            Auth::login($user);
            $request->session()->regenerate();
            return response()->json(['status' => 'success']);
        }

        return response()->json(['status' => 'error', 'message' => 'Registration failed'], 401);
    }
}
