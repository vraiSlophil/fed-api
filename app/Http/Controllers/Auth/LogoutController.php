<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\AuthActionService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Authentication
 *
 * Endpoints for access tokens, refresh tokens, email verification, and credential recovery.
 */
class LogoutController extends Controller
{
    /**
     * Initialize the controller with authentication command handlers.
     *
     * @param  AuthActionService  $actionService  Service that revokes active authentication tokens.
     */
    public function __construct(private readonly AuthActionService $actionService) {}

    /**
     * Revoke tokens for the authenticated user and terminate the current session.
     *
     * @param  Request  $request  Request that provides the currently authenticated user.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @responseFile 200 resources/docs/responses/success.json {"message_code":"auth.logout.success"}
     * @responseFile 401 resources/docs/responses/errors/auth-failed.json
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->actionService->logout($request->user());

        return ApiResponse::builder()
            ->success()
            ->messageCode('auth.logout.success')
            ->json();
    }
}
