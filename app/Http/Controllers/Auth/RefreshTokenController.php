<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\RevokedRefreshToken;
use App\Models\User;
use App\Support\Auth\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class RefreshTokenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $refreshToken = $this->extractRefreshToken($request);

        if (!$refreshToken) {
            throw new ApiException('auth.refresh.missing', [], 401, 'Refresh token missing');
        }

        $revoked = $this->findRevokedRefreshToken($refreshToken);
        if ($revoked) {
            if ($this->isWithinReuseGrace($revoked)) {
                $user = User::query()->find($revoked->user_id);
                if (!$user instanceof User) {
                    throw new ApiException('auth.refresh.invalid', [], 401, 'Invalid refresh token');
                }

                if ($user->isBlocked()) {
                    throw new ApiException('auth.blocked', [], 403, 'User blocked');
                }

                $tokens = app(TokenService::class)->issueTokensFor($user);

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

            $this->revokeAllTokensForUser($revoked->user_id);
            throw new ApiException('auth.refresh.reused', [], 401, 'Refresh token reused');
        }

        return DB::transaction(function () use ($refreshToken): JsonResponse {
            $token = $this->findRefreshTokenForUpdate($refreshToken);

            if (!$token) {
                throw new ApiException('auth.refresh.invalid', [], 401, 'Invalid refresh token');
            }

            if ($token->cant(TokenService::REFRESH_ABILITY)) {
                throw new ApiException('auth.refresh.invalid', [], 401, 'Invalid refresh token');
            }

            if ($token->expires_at && $token->expires_at->isPast()) {
                $token->delete();
                throw new ApiException('auth.refresh.expired', [], 401, 'Refresh token expired');
            }

            $user = $token->tokenable;

            if (!$user instanceof User) {
                $token->delete();
                throw new ApiException('auth.refresh.invalid', [], 401, 'Invalid refresh token');
            }

            if ($user->isBlocked()) {
                throw new ApiException('auth.blocked', [], 403, 'User blocked');
            }

            // Rotation: invalidate the used refresh token and issue new pair
            $this->storeRevokedRefreshToken($token, $user);
            $token->delete();

            $tokens = app(TokenService::class)->issueTokensFor($user);

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
        });
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

    private function storeRevokedRefreshToken(PersonalAccessToken $token, User $user): void
    {
        RevokedRefreshToken::create([
            'token_id' => $token->getKey(),
            'user_id' => $user->getAuthIdentifier(),
            'token_hash' => $token->token,
            'revoked_at' => now(),
            'expires_at' => $token->expires_at,
        ]);
    }

    private function findRevokedRefreshToken(string $rawToken): ?RevokedRefreshToken
    {
        $hash = $this->hashToken($rawToken);

        if ($hash === null) {
            return null;
        }

        return RevokedRefreshToken::where('token_hash', $hash)->first();
    }

    private function isWithinReuseGrace(RevokedRefreshToken $revoked): bool
    {
        $graceSeconds = (int)config('auth_tokens.refresh_reuse_grace_seconds', 0);

        if ($graceSeconds <= 0 || $revoked->revoked_at === null) {
            return false;
        }

        return $revoked->revoked_at->addSeconds($graceSeconds)->isFuture();
    }

    private function findRefreshTokenForUpdate(string $rawToken): ?PersonalAccessToken
    {
        if ($rawToken === '') {
            return null;
        }

        if (str_contains($rawToken, '|')) {
            [$id, $plain] = explode('|', $rawToken, 2);
            if (!ctype_digit($id) || $plain === '') {
                return null;
            }

            $token = PersonalAccessToken::whereKey((int) $id)
                ->lockForUpdate()
                ->first();

            if (!$token) {
                return null;
            }

            return hash_equals($token->token, hash('sha256', $plain)) ? $token : null;
        }

        $hash = hash('sha256', $rawToken);

        return PersonalAccessToken::where('token', $hash)
            ->lockForUpdate()
            ->first();
    }

    private function hashToken(string $rawToken): ?string
    {
        if ($rawToken === '') {
            return null;
        }

        if (str_contains($rawToken, '|')) {
            [, $rawToken] = explode('|', $rawToken, 2);
        }

        return hash('sha256', $rawToken);
    }

    private function revokeAllTokensForUser(string $userId): void
    {
        PersonalAccessToken::where('tokenable_id', $userId)
            ->where('tokenable_type', User::class)
            ->delete();
    }
}
