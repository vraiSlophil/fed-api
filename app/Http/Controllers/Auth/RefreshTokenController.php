<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\AuthActionService;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authentication endpoints for public account lifecycle actions.
 *
 * @group Authentication
 */
class RefreshTokenController extends Controller
{
    /**
     * Initialize the controller with authentication command handlers.
     *
     * @param  AuthActionService  $actionService  Service that validates and rotates refresh tokens.
     */
    public function __construct(private readonly AuthActionService $actionService) {}

    /**
     * Rotate the refresh token and return a new token pair with expiration metadata.
     *
     * @param  Request  $request  Request containing the refresh token in headers or bearer token.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @throws \App\Exceptions\ApiException When the operation cannot be completed.
     *
     * @unauthenticated
     *
     * @header X-Refresh-Token string optional Refresh token sent in a dedicated header. Example: 2|example-refresh-token
     * @header Authorization string optional Bearer refresh token fallback used when the dedicated header is absent. Example: Bearer 2|example-refresh-token
     *
     * @response 200 {
     *   "status": "success",
     *   "message": "Ok",
     *   "message_code": "auth.refresh.success",
     *   "data": {
     *     "access_token": "3|example-access-token",
     *     "refresh_token": "4|example-refresh-token",
     *     "access_expires_at": "2026-03-10T10:30:00+00:00",
     *     "refresh_expires_at": "2026-04-09T10:15:00+00:00"
     *   }
     * }
     */
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

    /**
     * Extract the refresh token from request headers or bearer token.
     *
     * @param  Request  $request  Request inspected for `X-Refresh-Token` or bearer token credentials.
     * @return ?string Raw refresh token value, or null when no token is present.
     */
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
