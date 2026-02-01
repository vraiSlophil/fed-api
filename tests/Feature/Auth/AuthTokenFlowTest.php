<?php

use App\Models\User;
use App\Support\Auth\TokenService;
use Laravel\Sanctum\PersonalAccessToken;

it('login renvoie un token et permet d\'appeler une route protégée', function () {
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

it('une route protégée renvoie 401 sans token', function () {
    $this->getJson('/api/auth/ping')->assertStatus(401);
});

it('logout révoque le token courant (suppression en base)', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $token = $user->createToken('test-token')->plainTextToken;

    // sanity check: le token existe
    expect(PersonalAccessToken::where('tokenable_id', $user->getAuthIdentifier())->count())
        ->toBeGreaterThan(0);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/auth/logout')
        ->assertStatus(200);

    // Le token doit être supprimé en base (source de vérité)
    expect(PersonalAccessToken::where('tokenable_id', $user->getAuthIdentifier())->count())
        ->toBe(0);
});

it('un refresh token ne peut pas accéder aux routes protégées', function () {
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
