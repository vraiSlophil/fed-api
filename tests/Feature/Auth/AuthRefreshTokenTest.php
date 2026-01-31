<?php

use App\Models\User;
use App\Support\Auth\TokenService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

it('refresh échoue si le refresh token est manquant', function () {
    $response = $this->postJson('/api/auth/refresh');

    $response->assertStatus(401);
    expect($response->json('message_code'))->toBe('auth.refresh.missing');
});

it('refresh échoue si le refresh token est invalide', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $accessToken = $user->createToken(
        'access-token',
        [TokenService::ACCESS_ABILITY],
        CarbonImmutable::now()->addMinutes(15)
    )->plainTextToken;

    $response = $this->withHeader('X-Refresh-Token', $accessToken)
        ->postJson('/api/auth/refresh');

    $response->assertStatus(401);
    expect($response->json('message_code'))->toBe('auth.refresh.invalid');
});

it('refresh renvoie un nouveau couple access/refresh et invalide l’ancien refresh', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $refreshExpiresAt = CarbonImmutable::now()->addDays(30);
    $refreshToken = $user->createToken(
        'refresh-token',
        [TokenService::REFRESH_ABILITY],
        $refreshExpiresAt
    )->plainTextToken;

    $response = $this->withHeader('X-Refresh-Token', $refreshToken)
        ->postJson('/api/auth/refresh');

    $response->assertStatus(200);
    expect($response->json('data.access_token'))->toBeString();
    expect($response->json('data.refresh_token'))->toBeString();

    // The old refresh token should be invalid after rotation
    expect(PersonalAccessToken::findToken($refreshToken))->toBeNull();
});

it('refresh verrouille le token pour éviter les refresh concurrents', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $refreshToken = $user->createToken(
        'refresh-token',
        [TokenService::REFRESH_ABILITY],
        CarbonImmutable::now()->addDays(30)
    )->plainTextToken;

    $queries = [];
    $capturing = true;

    DB::listen(function ($query) use (&$queries, &$capturing) {
        if ($capturing) {
            $queries[] = $query->sql;
        }
    });

    $this->withHeader('X-Refresh-Token', $refreshToken)
        ->postJson('/api/auth/refresh')
        ->assertStatus(200);

    $capturing = false;

    $matched = collect($queries)->contains(function ($sql) {
        $sql = strtolower($sql);
        return str_contains($sql, 'personal_access_tokens') && str_contains($sql, 'for update');
    });

    expect($matched)->toBeTrue();
});

it('refresh accepte la réutilisation dans la fenêtre de grace', function () {
    config(['auth_tokens.refresh_reuse_grace_seconds' => 30]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $refreshToken = $user->createToken(
        'refresh-token',
        [TokenService::REFRESH_ABILITY],
        CarbonImmutable::now()->addDays(30)
    )->plainTextToken;

    $this->withHeader('X-Refresh-Token', $refreshToken)
        ->postJson('/api/auth/refresh')
        ->assertStatus(200);

    $response = $this->withHeader('X-Refresh-Token', $refreshToken)
        ->postJson('/api/auth/refresh');

    $response->assertStatus(200);
    expect($response->json('message_code'))->toBe('auth.refresh.success');
});

it('refresh détecte la réutilisation d’un refresh token et révoque tous les tokens', function () {
    config(['auth_tokens.refresh_reuse_grace_seconds' => 0]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $refreshToken = $user->createToken(
        'refresh-token',
        [TokenService::REFRESH_ABILITY],
        CarbonImmutable::now()->addDays(30)
    )->plainTextToken;

    $this->withHeader('X-Refresh-Token', $refreshToken)
        ->postJson('/api/auth/refresh')
        ->assertStatus(200);

    $response = $this->withHeader('X-Refresh-Token', $refreshToken)
        ->postJson('/api/auth/refresh');

    $response->assertStatus(401);
    expect($response->json('message_code'))->toBe('auth.refresh.reused');

    expect(PersonalAccessToken::where('tokenable_id', $user->getAuthIdentifier())->count())
        ->toBe(0);
});

it('refresh échoue si le refresh token est expiré', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $refreshExpiresAt = CarbonImmutable::now()->subMinute();
    $refreshToken = $user->createToken(
        'refresh-token',
        [TokenService::REFRESH_ABILITY],
        $refreshExpiresAt
    )->plainTextToken;

    $response = $this->withHeader('X-Refresh-Token', $refreshToken)
        ->postJson('/api/auth/refresh');

    $response->assertStatus(401);
    expect($response->json('message_code'))->toBe('auth.refresh.expired');
});

it('refresh renvoie auth.blocked si l’utilisateur est bloqué', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'blocked_at' => now(),
    ]);

    $refreshExpiresAt = CarbonImmutable::now()->addDays(30);
    $refreshToken = $user->createToken(
        'refresh-token',
        [TokenService::REFRESH_ABILITY],
        $refreshExpiresAt
    )->plainTextToken;

    $response = $this->withHeader('X-Refresh-Token', $refreshToken)
        ->postJson('/api/auth/refresh');

    $response->assertStatus(403);
    expect($response->json('message_code'))->toBe('auth.blocked');
});
