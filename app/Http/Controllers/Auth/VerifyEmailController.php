<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\AuthActionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class VerifyEmailController extends Controller
{
    public function __construct(private readonly AuthActionService $actionService) {}

    public function __invoke(VerifyEmailRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->actionService->verifyEmail($validated['id'], (string) $validated['hash']);

        if ($result['already_verified']) {
            return ApiResponse::success(
                data: null,
                message: 'Email already verified',
                status: 200,
                messageCode: 'auth.verification.already_verified',
                messageParams: []
            );
        }

        return ApiResponse::success(
            data: null,
            message: 'Email verified',
            status: 200,
            messageCode: 'auth.verification.success',
            messageParams: []
        );
    }
}
