<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\AuthActionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Authentication endpoints for public account lifecycle actions.
 *
 * @group Authentication
 */
class RegisterController extends Controller
{
    /**
     * Initialize the controller with authentication command handlers.
     *
     * @param  AuthActionService  $actionService  Service that creates accounts and issues tokens.
     */
    public function __construct(private readonly AuthActionService $actionService) {}

    /**
     * Register a user account and return the initial authenticated session payload.
     *
     * @param  RegisterRequest  $request  Request carrying validated registration fields.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @unauthenticated
     *
     * @response 201 {
     *   "status": "success",
     *   "message": "Account created",
     *   "message_code": "auth.register.success",
     *   "data": {
     *     "user": {
     *       "user_id": "2a7188b7-8fd0-4bb9-9f9c-e61c3f4f7b24",
     *       "username": "john",
     *       "email": "john@example.com",
     *       "email_verified_at": null
     *     },
     *     "access_token": "1|example-access-token",
     *     "refresh_token": "2|example-refresh-token",
     *     "access_expires_at": "2026-03-10T10:15:00+00:00",
     *     "refresh_expires_at": "2026-04-09T10:00:00+00:00"
     *   }
     * }
     */
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $payload = $this->actionService->register($request->validated(), $request);

        return ApiResponse::builder()
            ->success(201, 'Account created')
            ->messageCode('auth.register.success')
            ->data($payload)
            ->json();
    }
}
