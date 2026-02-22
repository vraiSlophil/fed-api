<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\AuthActionService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    public function __construct(private readonly AuthActionService $actionService) {}

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
