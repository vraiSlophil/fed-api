<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return ApiResponse::builder()
                ->success()
                ->messageCode('email.verification.already_verified')
                ->json();
        }

        $request->user()->sendEmailVerificationNotification();

        return ApiResponse::builder()
            ->success()
            ->messageCode('email.verification.sent')
            ->json();
    }
}
