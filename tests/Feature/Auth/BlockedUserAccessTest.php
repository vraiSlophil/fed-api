<?php

use App\Domain\Auth\Services\TokenService;
use App\Models\Auth\User;

it('returns auth.blocked for blocked users calling access-token routes with an access token', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'blocked_at' => now(),
    ]);

    $accessToken = $user->createToken(
        'access-token',
        [TokenService::ACCESS_ABILITY],
        now()->addMinutes(15)
    )->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->getJson('/api/auth/ping')
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'auth.blocked');
});

it('returns auth.blocked for blocked users calling access-token routes with a refresh token', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'blocked_at' => now(),
    ]);

    $refreshToken = $user->createToken(
        'refresh-token',
        [TokenService::REFRESH_ABILITY],
        now()->addDays(30)
    )->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$refreshToken)
        ->getJson('/api/auth/ping')
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'auth.blocked');
});
