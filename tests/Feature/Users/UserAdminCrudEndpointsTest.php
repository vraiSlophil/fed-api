<?php

use App\Models\Auth\User;

it('allows admin to create and delete users through /api/users endpoints', function () {
    $admin = User::factory()->create([
        'role_power' => 100,
    ]);

    actingAsAccessUser($admin);

    $storeResponse = $this->postJson('/api/users', [
        'username' => 'created-by-admin',
        'email' => 'created-by-admin@example.test',
        'password' => 'Secret-password1!',
        'password_confirmation' => 'Secret-password1!',
        'role_power' => 10,
    ]);

    $storeResponse->assertCreated()
        ->assertJsonPath('message_code', 'user.create.success');

    $createdUserId = (string) $storeResponse->json('data.user_id');
    expect($createdUserId)->not->toBe('');

    $this->deleteJson("/api/users/{$createdUserId}")
        ->assertOk()
        ->assertJsonPath('message_code', 'user.delete.success');

    expect(User::query()->where('user_id', $createdUserId)->exists())->toBeFalse();
});

it('forbids non-admin from creating or deleting users', function () {
    $user = User::factory()->create([
        'role_power' => 10,
    ]);
    $target = User::factory()->create();

    actingAsAccessUser($user);

    $this->postJson('/api/users', [
        'username' => 'forbidden-create',
        'email' => 'forbidden-create@example.test',
        'password' => 'Secret-password1!',
        'password_confirmation' => 'Secret-password1!',
        'role_power' => 10,
    ])
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');

    $this->deleteJson("/api/users/{$target->user_id}")
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');
});

it('prevents admin self deletion', function () {
    $admin = User::factory()->create([
        'role_power' => 100,
    ]);

    actingAsAccessUser($admin);

    $this->deleteJson("/api/users/{$admin->user_id}")
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');
});
