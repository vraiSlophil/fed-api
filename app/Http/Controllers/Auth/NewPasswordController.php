<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\AuthActionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class NewPasswordController extends Controller
{
    /**
     * Initialize the controller with authentication command handlers.
     *
     * @param  AuthActionService  $actionService  Service that manages password reset workflows.
     */
    public function __construct(private readonly AuthActionService $actionService) {}

    /**
     * Reset the account password using a validated reset token payload.
     *
     * @param  ResetPasswordRequest  $request  Request carrying validated token, email, and new password fields.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        $this->actionService->resetPassword($request->validated());

        return ApiResponse::success(
            data: null,
            message: 'Ok',
            status: 200,
            messageCode: 'auth.reset.success',
            messageParams: []
        );
    }
}
