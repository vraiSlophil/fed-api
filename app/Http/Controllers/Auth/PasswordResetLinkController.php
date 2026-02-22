<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\AuthActionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class PasswordResetLinkController extends Controller
{
    public function __construct(private readonly AuthActionService $actionService) {}

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
