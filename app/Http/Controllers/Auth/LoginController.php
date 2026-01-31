<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Responses\ApiResponse;
use App\Support\Auth\TokenService;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $user = $request->authenticate();

        if ($user->isBlocked()) {
            throw new ApiException('auth.blocked', [], 403, 'User blocked');
        }

        $user->last_login_at = now();
        $user->last_login_ip = $request->ip();
        $user->save();

        $tokens = app(TokenService::class)->issueTokensFor($user);

        return ApiResponse::builder()
            ->success()
            ->data([
                'user' => $user,
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'access_expires_at' => $tokens['access_expires_at'],
                'refresh_expires_at' => $tokens['refresh_expires_at'],
            ])
            ->messageCode('auth.login.success')
            ->json();
    }
}
