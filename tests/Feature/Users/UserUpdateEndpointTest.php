<?php

use App\Models\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

it('supports partial self updates via PATCH /api/users/{user}', function () {
    $user = User::factory()->create([
        'first_name' => 'Before',
    ]);

    actingAsAccessUser($user);

    $response = $this->patchJson("/api/users/{$user->user_id}", [
        'first_name' => 'After',
    ]);

    $response->assertOk();
    expect($response->json('message_code'))->toBe('user.update.success');
    expect($response->json('data.first_name'))->toBe('After');
});

it('supports combined self updates with identity, password, and avatar in one request', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'password' => Hash::make('Old-password-123!'),
    ]);

    actingAsAccessUser($user);

    $response = $this->patch("/api/users/{$user->user_id}", [
        'email' => 'updated-'.$user->user_id.'@example.com',
        'current_password' => 'Old-password-123!',
        'password' => 'New-password-123!',
        'password_confirmation' => 'New-password-123!',
        'avatar' => UploadedFile::fake()->image('avatar.jpg'),
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertOk();
    expect($response->json('message_code'))->toBe('user.update.email');

    $updated = $user->fresh();
    expect($updated->email_verified_at)->toBeNull();
    expect(Hash::check('New-password-123!', $updated->password))->toBeTrue();
    expect($updated->avatar_path)->not->toBeNull();
    Storage::disk('public')->assertExists($updated->avatar_path);
});

it('forbids non-admin users from updating another user account', function () {
    $actor = User::factory()->create();

    $target = User::factory()->create();

    actingAsAccessUser($actor);

    $response = $this->patchJson("/api/users/{$target->user_id}", [
        'first_name' => 'Unauthorized',
    ]);

    $response->assertForbidden();
    expect($response->json('message_code'))->toBe('permission.denied');
});

it('rejects admin-managed fields for non-admin users', function () {
    $user = User::factory()->create();

    actingAsAccessUser($user);

    $response = $this->patchJson("/api/users/{$user->user_id}", [
        'role_power' => 100,
    ]);

    $response->assertUnprocessable();
    expect($response->json('message_code'))->toBe('validation.invalid');
});

it('allows admin users to update role_power and blocked_at through PATCH /api/users/{user}', function () {
    $admin = User::factory()->create([
        'role_power' => 100,
    ]);

    $target = User::factory()->create([
        'role_power' => 10,
        'blocked_at' => null,
    ]);

    actingAsAccessUser($admin);

    $response = $this->patchJson("/api/users/{$target->user_id}", [
        'role_power' => 100,
        'blocked_at' => now()->toISOString(),
    ]);

    $response->assertOk();
    expect($response->json('message_code'))->toBe('user.update.success');
    expect($response->json('data.role_power'))->toBe(100);
    expect($response->json('data.blocked_at'))->not->toBeNull();
});

it('returns 404 on removed users block and unblock action routes', function () {
    $admin = User::factory()->create([
        'role_power' => 100,
    ]);

    $target = User::factory()->create();

    actingAsAccessUser($admin);

    $this->postJson("/api/users/{$target->user_id}/block")
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');

    $this->postJson("/api/users/{$target->user_id}/unblock")
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');
});
