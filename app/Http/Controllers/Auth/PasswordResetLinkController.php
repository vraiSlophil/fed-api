<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\AuthActionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class PasswordResetLinkController extends Controller
{
    /**
     * Initialize the controller with authentication command handlers.
     *
     * @param  AuthActionService  $actionService  Service that manages password reset workflows.
     */
    public function __construct(private readonly AuthActionService $actionService) {}

    /**
     * Dispatch a password reset link for the submitted email address.
     *
     * @param  ForgotPasswordRequest  $request  Request carrying a validated account email.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        $this->actionService->sendPasswordResetLink($request->validated('email'));

        return ApiResponse::success(
            data: null,
            message: 'Ok',
            status: 200,
            messageCode: 'auth.reset_link.sent',
            messageParams: []
        );
    }
}
