<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\AuthActionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Authentication endpoints for public account lifecycle actions.
 *
 * @group Authentication
 */
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
     *
     * @unauthenticated
     *
     * @response 200 {
     *   "status": "success",
     *   "message": "Ok",
     *   "message_code": "auth.login.success",
     *   "data": {
     *     "user": {
     *       "user_id": "2a7188b7-8fd0-4bb9-9f9c-e61c3f4f7b24",
     *       "username": "john",
     *       "email": "john@example.com"
     *     },
     *     "access_token": "1|example-access-token",
     *     "refresh_token": "2|example-refresh-token",
     *     "access_expires_at": "2026-03-10T10:15:00+00:00",
     *     "refresh_expires_at": "2026-04-09T10:00:00+00:00"
     *   }
     * }
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
