<?php

use App\Domain\Auth\Services\TokenService;
use App\Models\Auth\User;
use Laravel\Sanctum\Sanctum;

it('allows admin to create and delete users through /api/users endpoints', function () {
    $admin = User::factory()->create([
        'role_power' => 100,
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($admin, [TokenService::ACCESS_ABILITY]);

    $storeResponse = $this->postJson('/api/users', [
        'username' => 'created-by-admin',
        'email' => 'created-by-admin@example.test',
        'password' => 'Secret-password1!',
        'password_confirmation' => 'Secret-password1!',
        'role_power' => 10,
    ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('message_code', 'user.create.success');

    $createdUserId = (string) $storeResponse->json('data.user_id');
    expect($createdUserId)->not->toBe('');

    $this->deleteJson("/api/users/{$createdUserId}")
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'user.delete.success');

    expect(User::query()->where('user_id', $createdUserId)->exists())->toBeFalse();
});

it('forbids non-admin from creating or deleting users', function () {
    $user = User::factory()->create([
        'role_power' => 10,
        'email_verified_at' => now(),
    ]);
    $target = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $this->postJson('/api/users', [
        'username' => 'forbidden-create',
        'email' => 'forbidden-create@example.test',
        'password' => 'Secret-password1!',
        'password_confirmation' => 'Secret-password1!',
        'role_power' => 10,
    ])
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');

    $this->deleteJson("/api/users/{$target->user_id}")
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');
});

it('prevents admin self deletion', function () {
    $admin = User::factory()->create([
        'role_power' => 100,
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($admin, [TokenService::ACCESS_ABILITY]);

    $this->deleteJson("/api/users/{$admin->user_id}")
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');
});
