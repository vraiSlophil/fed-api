<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

class PasswordResetLinkController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = null;
        $user = User::where('email', $request->input('email'))->first();

        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (Throwable $e) {
            if ($user) {
                Log::error('Password reset email failed to dispatch', [
                    'user_id' => $user->user_id,
                    'error' => $e->getMessage(),
                ]);
            }

            throw new ApiException(
                messageCode: 'auth.reset_link.failed',
                messageParams: ['reason' => 'dispatch_failed'],
                status: 500,
                message: 'Reset link could not be sent'
            );
        }

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
