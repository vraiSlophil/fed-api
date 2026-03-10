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
class EmailVerificationNotificationController extends Controller
{
    /**
     * Initialize the controller with authentication command handlers.
     *
     * @param  AuthActionService  $actionService  Service that dispatches verification notifications.
     */
    public function __construct(private readonly AuthActionService $actionService) {}

    /**
     * Send a verification email unless the account is already verified.
     *
     * @param  Request  $request  Request that provides the currently authenticated user.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @responseFile 200 resources/docs/responses/success.json {"message_code":"email.verification.sent"}
     * @responseFile 401 resources/docs/responses/errors/auth-failed.json
     */
    public function __invoke(Request $request): JsonResponse
    {
        $sent = $this->actionService->sendVerificationNotification($request->user());

        if (! $sent) {
            return ApiResponse::builder()
                ->success()
                ->messageCode('email.verification.already_verified')
                ->json();
        }

        return ApiResponse::builder()
            ->success()
            ->messageCode('email.verification.sent')
            ->json();
    }
}
