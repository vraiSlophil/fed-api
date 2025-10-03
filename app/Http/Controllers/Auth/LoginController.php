<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $user = $request->user();
        $user->last_login_at = now();
        $user->last_login_ip = $request->ip();
        $user->save();

        $token = $user->createToken('sanctum-token')->plainTextToken;

        return ApiResponse::builder()
            ->success()
            ->data([
                'user' => $user,
                'token' => $token,
            ])
            ->json();
    }
}
