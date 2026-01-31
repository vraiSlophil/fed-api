<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            // Revoke all tokens (access + refresh) for this user.
            $user->tokens()->delete();
        }

        return ApiResponse::builder()
            ->success()
            ->messageCode('auth.logout.success')
            ->json();
    }
}
