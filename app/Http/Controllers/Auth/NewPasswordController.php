<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\AuthActionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class NewPasswordController extends Controller
{
    public function __construct(private readonly AuthActionService $actionService) {}

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
