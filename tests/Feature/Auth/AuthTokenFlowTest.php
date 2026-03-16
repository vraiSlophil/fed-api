<?php

use App\Domain\Auth\Services\TokenService;
use App\Models\Auth\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

it('login returns a token and allows calling a protected route', function () {
    $user = User::factory()->create([
        'password' => Hash::make('Secret-password1!'),
    ]);

    $login = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'Secret-password1!',
    ]);

    $login->assertOk();
    expect($login->json('data.access_token'))->toBeString();

    $token = $login->json('data.access_token');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/auth/ping')
        ->assertOk();
});

it('a protected route returns 401 without a token', function () {
    $this->getJson('/api/auth/ping')->assertUnauthorized();
});

it('logout revokes the current token (database deletion)', function () {
    $user = User::factory()->create();

    $token = $user->createToken('test-token')->plainTextToken;

    // Sanity check: the token exists.
    expect(PersonalAccessToken::where('tokenable_id', $user->getAuthIdentifier())->count())
        ->toBeGreaterThan(0);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/auth/logout')
        ->assertOk();

    // The token must be deleted from the database (source of truth).
    expect(PersonalAccessToken::where('tokenable_id', $user->getAuthIdentifier())->count())
        ->toBe(0);
});

it('a refresh token cannot access protected routes', function () {
    $user = User::factory()->create();

    $refreshToken = $user->createToken(
        'refresh-token',
        [TokenService::REFRESH_ABILITY],
        now()->addDays(30)
    )->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$refreshToken)
        ->getJson('/api/auth/ping')
        ->assertForbidden();
});
