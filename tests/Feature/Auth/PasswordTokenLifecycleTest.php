<?php

use App\Domain\Auth\Services\TokenService;
use App\Models\Auth\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\PersonalAccessToken;

it('password reset revokes all active tokens and invalidates previously issued access tokens', function () {
    $oldPassword = 'Old-password-123!';
    $newPassword = 'New-password-123!';

    $user = User::factory()->create([
        'password' => Hash::make($oldPassword),
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
        ->assertOk();

    $resetToken = Password::createToken($user);

    $response = $this->postJson('/api/auth/reset-password', [
        'email' => $user->email,
        'token' => $resetToken,
        'password' => $newPassword,
        'password_confirmation' => $newPassword,
    ]);

    $response->assertOk();
    expect($response->json('message_code'))->toBe('auth.reset.success');
    expect(PersonalAccessToken::where('tokenable_id', $user->getAuthIdentifier())->count())
        ->toBe(0);
    expect(PersonalAccessToken::findToken($accessToken))->toBeNull();

    auth()->forgetGuards();
    $expiredSession = $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->getJson('/api/auth/ping');

    $expiredSession->assertUnauthorized();
    expect($expiredSession->json('message_code'))->toBe('auth.failed');

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => $newPassword,
    ])->assertOk();
});

it('user PATCH password update revokes all active tokens and invalidates the current access token', function () {
    $oldPassword = 'Current-password-123!';
    $newPassword = 'Updated-password-123!';

    $user = User::factory()->create([
        'password' => Hash::make($oldPassword),
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
        ->patchJson("/api/users/{$user->user_id}", [
            'current_password' => $oldPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

    $response->assertOk();
    expect($response->json('message_code'))->toBe('user.update.success');
    expect(PersonalAccessToken::where('tokenable_id', $user->getAuthIdentifier())->count())
        ->toBe(0);
    expect(PersonalAccessToken::findToken($accessToken))->toBeNull();

    auth()->forgetGuards();
    $expiredSession = $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->getJson('/api/auth/ping');

    $expiredSession->assertUnauthorized();
    expect($expiredSession->json('message_code'))->toBe('auth.failed');

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => $newPassword,
    ])->assertOk();
});

it('admin PATCH password update revokes all active tokens for the target user', function () {
    $newPassword = 'Admin-reset-password-123!';

    $admin = User::factory()->create([
        'role_power' => 100,
    ]);

    $target = User::factory()->create([
        'password' => Hash::make('Target-password-123!'),
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
        ->patchJson("/api/users/{$target->user_id}", [
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

    $response->assertOk();
    expect($response->json('message_code'))->toBe('user.update.success');
    expect(PersonalAccessToken::where('tokenable_id', $target->getAuthIdentifier())->count())
        ->toBe(0);
    expect(PersonalAccessToken::findToken($targetAccessToken))->toBeNull();

    auth()->forgetGuards();
    $expiredTargetSession = $this->withHeader('Authorization', 'Bearer '.$targetAccessToken)
        ->getJson('/api/auth/ping');

    $expiredTargetSession->assertUnauthorized();
    expect($expiredTargetSession->json('message_code'))->toBe('auth.failed');
});

it('admin PATCH update without password does not revoke target user tokens', function () {
    $admin = User::factory()->create([
        'role_power' => 100,
    ]);

    $target = User::factory()->create();

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
        ->patchJson("/api/users/{$target->user_id}", [
            'first_name' => 'UpdatedByAdmin',
        ]);

    $response->assertOk();
    expect($response->json('message_code'))->toBe('user.update.success');
    expect(PersonalAccessToken::where('tokenable_id', $target->getAuthIdentifier())->count())
        ->toBe(2);

    $this->withHeader('Authorization', 'Bearer '.$targetAccessToken)
        ->getJson('/api/auth/ping')
        ->assertOk();
});

it('admin PATCH block revokes all active tokens and invalidates previously issued target tokens', function () {
    $admin = User::factory()->create([
        'role_power' => 100,
    ]);

    $target = User::factory()->create([
        'password' => Hash::make('Target-password-123!'),
        'blocked_at' => null,
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
        ->patchJson("/api/users/{$target->user_id}", [
            'blocked_at' => now()->toISOString(),
        ]);

    $response->assertOk();
    expect($response->json('message_code'))->toBe('user.update.success');
    expect(PersonalAccessToken::where('tokenable_id', $target->getAuthIdentifier())->count())
        ->toBe(0);
    expect(PersonalAccessToken::findToken($targetAccessToken))->toBeNull();

    auth()->forgetGuards();
    $expiredTargetSession = $this->withHeader('Authorization', 'Bearer '.$targetAccessToken)
        ->getJson('/api/auth/ping');

    $expiredTargetSession->assertUnauthorized();
    expect($expiredTargetSession->json('message_code'))->toBe('auth.failed');
});

it('a previously blocked user can login and receive tokens again after admin unblocks the account', function () {
    $admin = User::factory()->create([
        'role_power' => 100,
    ]);

    $targetPassword = 'Target-password-123!';
    $target = User::factory()->create([
        'password' => Hash::make($targetPassword),
        'blocked_at' => now(),
    ]);

    $adminAccessToken = $admin->createToken(
        'admin-access-token',
        [TokenService::ACCESS_ABILITY],
        now()->addMinutes(15)
    )->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$adminAccessToken)
        ->patchJson("/api/users/{$target->user_id}", [
            'blocked_at' => null,
        ]);

    $response->assertOk();
    expect($response->json('message_code'))->toBe('user.update.success');
    expect($response->json('data.blocked_at'))->toBeNull();

    $login = $this->postJson('/api/auth/login', [
        'email' => $target->email,
        'password' => $targetPassword,
    ]);

    $login->assertOk();
    expect($login->json('message_code'))->toBe('auth.login.success');
    expect($login->json('data.access_token'))->toBeString();
    expect($login->json('data.refresh_token'))->toBeString();
});
