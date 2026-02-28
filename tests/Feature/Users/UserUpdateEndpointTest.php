<?php

use App\Models\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

it('supports partial self updates via PATCH /api/users/{user}', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'first_name' => 'Before',
    ]);

    Sanctum::actingAs($user, ['access']);

    $response = $this->patchJson("/api/users/{$user->user_id}", [
        'first_name' => 'After',
    ]);

    $response->assertStatus(200);
    expect($response->json('message_code'))->toBe('user.update.success');
    expect($response->json('data.first_name'))->toBe('After');
});

it('supports combined self updates with identity, password, and avatar in one request', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'password' => bcrypt('old-password-123'),
    ]);

    Sanctum::actingAs($user, ['access']);

    $response = $this->patch("/api/users/{$user->user_id}", [
        'email' => 'updated-'.$user->user_id.'@example.com',
        'current_password' => 'old-password-123',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
        'avatar' => UploadedFile::fake()->image('avatar.jpg'),
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(200);
    expect($response->json('message_code'))->toBe('user.update.email');

    $updated = $user->fresh();
    expect($updated->email_verified_at)->toBeNull();
    expect(Hash::check('new-password-123', $updated->password))->toBeTrue();
    expect($updated->avatar_path)->not->toBeNull();
    Storage::disk('public')->assertExists($updated->avatar_path);
});

it('forbids non-admin users from updating another user account', function () {
    $actor = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $target = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($actor, ['access']);

    $response = $this->patchJson("/api/users/{$target->user_id}", [
        'first_name' => 'Unauthorized',
    ]);

    $response->assertStatus(403);
    expect($response->json('message_code'))->toBe('permission.denied');
});

it('rejects admin-managed fields for non-admin users', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($user, ['access']);

    $response = $this->patchJson("/api/users/{$user->user_id}", [
        'role_power' => 100,
    ]);

    $response->assertStatus(422);
    expect($response->json('message_code'))->toBe('validation.invalid');
});

it('allows admin users to update role_power and blocked_at through PATCH /api/users/{user}', function () {
    $admin = User::factory()->create([
        'role_power' => 100,
        'email_verified_at' => now(),
    ]);

    $target = User::factory()->create([
        'role_power' => 10,
        'blocked_at' => null,
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($admin, ['access']);

    $response = $this->patchJson("/api/users/{$target->user_id}", [
        'role_power' => 100,
        'blocked_at' => now()->toISOString(),
    ]);

    $response->assertStatus(200);
    expect($response->json('message_code'))->toBe('user.update.success');
    expect($response->json('data.role_power'))->toBe(100);
    expect($response->json('data.blocked_at'))->not->toBeNull();
});

it('returns 404 on removed users block and unblock action routes', function () {
    $admin = User::factory()->create([
        'role_power' => 100,
        'email_verified_at' => now(),
    ]);

    $target = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($admin, ['access']);

    $this->postJson("/api/users/{$target->user_id}/block")
        ->assertStatus(404)
        ->assertJsonPath('message_code', 'resource.not_found');

    $this->postJson("/api/users/{$target->user_id}/unblock")
        ->assertStatus(404)
        ->assertJsonPath('message_code', 'resource.not_found');
});
