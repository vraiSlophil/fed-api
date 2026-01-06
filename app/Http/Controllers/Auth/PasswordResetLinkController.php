<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordResetLinkController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            throw new ApiException(
                messageCode: 'auth.reset_link.failed',
                messageParams: ['reason' => $status],
                status: 400,
                message: 'Reset link could not be sent'
            );
        }

        return ApiResponse::success(
            data: null,
            message: 'Ok',
            status: 200,
            messageCode: 'auth.reset_link.sent',
            messageParams: []
        );
    }
}
