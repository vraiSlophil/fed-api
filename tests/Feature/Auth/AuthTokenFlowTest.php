<?php

use App\Domain\Auth\Services\TokenService;
use App\Models\Auth\User;
use Laravel\Sanctum\PersonalAccessToken;

it('login returns a token and allows calling a protected route', function () {
    $user = User::factory()->create([
        'password' => bcrypt('secret-password'),
        'email_verified_at' => now(),
    ]);

    $login = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'secret-password',
    ]);

    $login->assertStatus(200);
    expect($login->json('data.access_token'))->toBeString();

    $token = $login->json('data.access_token');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/auth/ping')
        ->assertStatus(200);
});

it('a protected route returns 401 without a token', function () {
    $this->getJson('/api/auth/ping')->assertStatus(401);
});

it('logout revokes the current token (database deletion)', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $token = $user->createToken('test-token')->plainTextToken;

    // Sanity check: the token exists.
    expect(PersonalAccessToken::where('tokenable_id', $user->getAuthIdentifier())->count())
        ->toBeGreaterThan(0);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/auth/logout')
        ->assertStatus(200);

    // The token must be deleted from the database (source of truth).
    expect(PersonalAccessToken::where('tokenable_id', $user->getAuthIdentifier())->count())
        ->toBe(0);
});

it('a refresh token cannot access protected routes', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $refreshToken = $user->createToken(
        'refresh-token',
        [TokenService::REFRESH_ABILITY],
        now()->addDays(30)
    )->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$refreshToken)
        ->getJson('/api/auth/ping')
        ->assertStatus(403);
});
