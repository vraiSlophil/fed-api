<?php

namespace App\Domain\Auth\Services;

use App\Models\Auth\User;
use Carbon\CarbonImmutable;

class TokenService
{
    public const ACCESS_ABILITY = 'access';

    public const REFRESH_ABILITY = 'refresh';

    public function issueTokensFor(User $user): array
    {
        $accessTtlMinutes = (int) config('auth_tokens.access_ttl_minutes', 15);
        $refreshTtlDays = (int) config('auth_tokens.refresh_ttl_days', 30);

        $accessExpiresAt = CarbonImmutable::now()->addMinutes($accessTtlMinutes);
        $refreshExpiresAt = CarbonImmutable::now()->addDays($refreshTtlDays);

        $accessToken = $user->createToken(
            'access-token',
            [self::ACCESS_ABILITY],
            $accessExpiresAt
        )->plainTextToken;

        $refreshToken = $user->createToken(
            'refresh-token',
            [self::REFRESH_ABILITY],
            $refreshExpiresAt
        )->plainTextToken;

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'access_expires_at' => $accessExpiresAt->toIso8601String(),
            'refresh_expires_at' => $refreshExpiresAt->toIso8601String(),
        ];
    }
}
