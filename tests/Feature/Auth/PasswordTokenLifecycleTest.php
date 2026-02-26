<?php

use App\Domain\Auth\Services\TokenService;
use App\Models\Auth\User;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\PersonalAccessToken;

it('password reset revokes all active tokens and invalidates previously issued access tokens', function () {
    $oldPassword = 'old-password-123';
    $newPassword = 'new-password-123';

    $user = User::factory()->create([
        'password' => bcrypt($oldPassword),
        'email_verified_at' => now(),
    ]);

    $accessToken = $user->createToken(
        'access-token',
        [TokenService::ACCESS_ABILITY],
        now()->addMinutes(15)
    )->plainTextToken;

    $user->createToken(
        'refresh-token',
        [TokenService::REFRESH_ABILITY],
        now()->addDays(30)
    )->plainTextToken;

    expect(PersonalAccessToken::where('tokenable_id', $user->getAuthIdentifier())->count())
        ->toBe(2);

    $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->getJson('/api/auth/ping')
        ->assertStatus(200);

    $resetToken = Password::createToken($user);

    $response = $this->postJson('/api/auth/reset-password', [
        'email' => $user->email,
        'token' => $resetToken,
        'password' => $newPassword,
        'password_confirmation' => $newPassword,
    ]);

    $response->assertStatus(200);
    expect($response->json('message_code'))->toBe('auth.reset.success');
    expect(PersonalAccessToken::where('tokenable_id', $user->getAuthIdentifier())->count())
        ->toBe(0);
    expect(PersonalAccessToken::findToken($accessToken))->toBeNull();

    auth()->forgetGuards();
    $expiredSession = $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->getJson('/api/auth/ping');

    $expiredSession->assertStatus(401);
    expect($expiredSession->json('message_code'))->toBe('auth.failed');

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => $newPassword,
    ])->assertStatus(200);
});

it('profile password update revokes all active tokens and invalidates the current access token', function () {
    $oldPassword = 'current-password-123';
    $newPassword = 'updated-password-123';

    $user = User::factory()->create([
        'password' => bcrypt($oldPassword),
        'email_verified_at' => now(),
    ]);

    $accessToken = $user->createToken(
        'access-token',
        [TokenService::ACCESS_ABILITY],
        now()->addMinutes(15)
    )->plainTextToken;

    $user->createToken(
        'refresh-token',
        [TokenService::REFRESH_ABILITY],
        now()->addDays(30)
    )->plainTextToken;

    expect(PersonalAccessToken::where('tokenable_id', $user->getAuthIdentifier())->count())
        ->toBe(2);

    $response = $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->postJson('/api/profile/password', [
            'current_password' => $oldPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

    $response->assertStatus(200);
    expect($response->json('message_code'))->toBe('auth.password.updated');
    expect(PersonalAccessToken::where('tokenable_id', $user->getAuthIdentifier())->count())
        ->toBe(0);
    expect(PersonalAccessToken::findToken($accessToken))->toBeNull();

    auth()->forgetGuards();
    $expiredSession = $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->getJson('/api/auth/ping');

    $expiredSession->assertStatus(401);
    expect($expiredSession->json('message_code'))->toBe('auth.failed');

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => $newPassword,
    ])->assertStatus(200);
});

it('admin password update revokes all active tokens for the target user', function () {
    $newPassword = 'admin-reset-password-123';

    $admin = User::factory()->create([
        'role_power' => 100,
        'email_verified_at' => now(),
    ]);

    $target = User::factory()->create([
        'password' => bcrypt('target-password-123'),
        'email_verified_at' => now(),
    ]);

    $adminAccessToken = $admin->createToken(
        'admin-access-token',
        [TokenService::ACCESS_ABILITY],
        now()->addMinutes(15)
    )->plainTextToken;

    $targetAccessToken = $target->createToken(
        'target-access-token',
        [TokenService::ACCESS_ABILITY],
        now()->addMinutes(15)
    )->plainTextToken;

    $target->createToken(
        'target-refresh-token',
        [TokenService::REFRESH_ABILITY],
        now()->addDays(30)
    )->plainTextToken;

    expect(PersonalAccessToken::where('tokenable_id', $target->getAuthIdentifier())->count())
        ->toBe(2);

    $response = $this->withHeader('Authorization', 'Bearer '.$adminAccessToken)
        ->postJson("/api/admin/users/{$target->user_id}", [
            'username' => $target->username,
            'email' => $target->email,
            'first_name' => $target->first_name,
            'last_name' => $target->last_name,
            'role_power' => $target->role_power,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

    $response->assertStatus(200);
    expect($response->json('message_code'))->toBe('user.update.success');
    expect(PersonalAccessToken::where('tokenable_id', $target->getAuthIdentifier())->count())
        ->toBe(0);
    expect(PersonalAccessToken::findToken($targetAccessToken))->toBeNull();

    auth()->forgetGuards();
    $expiredTargetSession = $this->withHeader('Authorization', 'Bearer '.$targetAccessToken)
        ->getJson('/api/auth/ping');

    $expiredTargetSession->assertStatus(401);
    expect($expiredTargetSession->json('message_code'))->toBe('auth.failed');
});

it('admin update without password does not revoke target user tokens', function () {
    $admin = User::factory()->create([
        'role_power' => 100,
        'email_verified_at' => now(),
    ]);

    $target = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $adminAccessToken = $admin->createToken(
        'admin-access-token',
        [TokenService::ACCESS_ABILITY],
        now()->addMinutes(15)
    )->plainTextToken;

    $targetAccessToken = $target->createToken(
        'target-access-token',
        [TokenService::ACCESS_ABILITY],
        now()->addMinutes(15)
    )->plainTextToken;

    $target->createToken(
        'target-refresh-token',
        [TokenService::REFRESH_ABILITY],
        now()->addDays(30)
    )->plainTextToken;

    expect(PersonalAccessToken::where('tokenable_id', $target->getAuthIdentifier())->count())
        ->toBe(2);

    $response = $this->withHeader('Authorization', 'Bearer '.$adminAccessToken)
        ->postJson("/api/admin/users/{$target->user_id}", [
            'username' => $target->username,
            'email' => $target->email,
            'first_name' => $target->first_name,
            'last_name' => $target->last_name,
            'role_power' => $target->role_power,
        ]);

    $response->assertStatus(200);
    expect($response->json('message_code'))->toBe('user.update.success');
    expect(PersonalAccessToken::where('tokenable_id', $target->getAuthIdentifier())->count())
        ->toBe(2);

    $this->withHeader('Authorization', 'Bearer '.$targetAccessToken)
        ->getJson('/api/auth/ping')
        ->assertStatus(200);
});
