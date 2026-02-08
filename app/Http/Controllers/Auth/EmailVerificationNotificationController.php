<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmailVerificationNotificationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return ApiResponse::builder()
                ->success()
                ->messageCode('email.verification.already_verified')
                ->json();
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            Log::error('Email verification notification failed to dispatch', [
                'user_id' => $request->user()->user_id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::builder()
                ->error()
                ->messageCode('email.verification.failed')
                ->status(500)
                ->json();
        }

        return ApiResponse::builder()
            ->success()
            ->messageCode('email.verification.sent')
            ->json();
    }
}
