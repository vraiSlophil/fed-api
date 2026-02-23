<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\AuthActionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    /**
     * Initialize the controller with authentication command handlers.
     *
     * @param  AuthActionService  $actionService  Service that logs users in and issues tokens.
     */
    public function __construct(private readonly AuthActionService $actionService) {}

    /**
     * Authenticate the provided credentials and return the authenticated session payload.
     *
     * @param  LoginRequest  $request  Request carrying validated email/password credentials.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $user = $request->authenticate();
        $payload = $this->actionService->login($user, $request);

        return ApiResponse::builder()
            ->success()
            ->data($payload)
            ->messageCode('auth.login.success')
            ->json();
    }
}
