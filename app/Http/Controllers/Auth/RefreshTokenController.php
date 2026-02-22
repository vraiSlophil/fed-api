<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\AuthActionService;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefreshTokenController extends Controller
{
    public function __construct(private readonly AuthActionService $actionService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $refreshToken = $this->extractRefreshToken($request);

        if (! $refreshToken) {
            throw new ApiException('auth.refresh.missing', [], 401, 'Refresh token missing');
        }

        $tokens = $this->actionService->refresh($refreshToken);

        return ApiResponse::builder()
            ->success()
            ->messageCode('auth.refresh.success')
            ->data([
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'access_expires_at' => $tokens['access_expires_at'],
                'refresh_expires_at' => $tokens['refresh_expires_at'],
            ])
            ->json();
    }

    private function extractRefreshToken(Request $request): ?string
    {
        $refreshHeader = $request->header('X-Refresh-Token');
        if (is_string($refreshHeader) && $refreshHeader !== '') {
            return $refreshHeader;
        }

        $bearer = $request->bearerToken();
        if (is_string($bearer) && $bearer !== '') {
            return $bearer;
        }

        return null;
    }
}
