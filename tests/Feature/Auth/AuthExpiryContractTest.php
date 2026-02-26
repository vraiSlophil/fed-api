<?php

use App\Domain\Auth\Services\TokenService;
use App\Models\Auth\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

it('login returns access and refresh expiry metadata', function () {
    $user = User::factory()->create([
        'password' => bcrypt('secret-password'),
        'email_verified_at' => now(),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'secret-password',
    ]);

    $response->assertStatus(200);

    $accessExpiresAt = $response->json('data.access_expires_at');
    $refreshExpiresAt = $response->json('data.refresh_expires_at');

    expect($accessExpiresAt)->toBeString();
    expect($refreshExpiresAt)->toBeString();

    $access = CarbonImmutable::parse($accessExpiresAt);
    $refresh = CarbonImmutable::parse($refreshExpiresAt);

    expect($access->isFuture())->toBeTrue();
    expect($refresh->isFuture())->toBeTrue();
    expect($refresh->greaterThan($access))->toBeTrue();
});

it('register returns access and refresh expiry metadata', function () {
    Notification::fake();

    $response = $this->postJson('/api/auth/register', [
        'username' => 'new-user-expiry',
        'email' => 'new-user-expiry@example.test',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ]);

    $response->assertStatus(201);

    $accessExpiresAt = $response->json('data.access_expires_at');
    $refreshExpiresAt = $response->json('data.refresh_expires_at');

    expect($accessExpiresAt)->toBeString();
    expect($refreshExpiresAt)->toBeString();

    $access = CarbonImmutable::parse($accessExpiresAt);
    $refresh = CarbonImmutable::parse($refreshExpiresAt);

    expect($access->isFuture())->toBeTrue();
    expect($refresh->isFuture())->toBeTrue();
    expect($refresh->greaterThan($access))->toBeTrue();
});

it('refresh returns access and refresh expiry metadata', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $refreshToken = $user->createToken(
        'refresh-token',
        [TokenService::REFRESH_ABILITY],
        now()->addDays(30)
    )->plainTextToken;

    $response = $this->withHeader('X-Refresh-Token', $refreshToken)
        ->postJson('/api/auth/refresh');

    $response->assertStatus(200);

    $accessExpiresAt = $response->json('data.access_expires_at');
    $refreshExpiresAt = $response->json('data.refresh_expires_at');

    expect($accessExpiresAt)->toBeString();
    expect($refreshExpiresAt)->toBeString();

    $access = CarbonImmutable::parse($accessExpiresAt);
    $refresh = CarbonImmutable::parse($refreshExpiresAt);

    expect($access->isFuture())->toBeTrue();
    expect($refresh->isFuture())->toBeTrue();
    expect($refresh->greaterThan($access))->toBeTrue();
});

it('expired access token is rejected on protected routes', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $expiredAccessToken = $user->createToken(
        'expired-access-token',
        [TokenService::ACCESS_ABILITY],
        now()->subMinute()
    )->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$expiredAccessToken)
        ->getJson('/api/auth/ping');

    $response->assertStatus(401);
    expect($response->json('message_code'))->toBe('auth.failed');
});
