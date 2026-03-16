<?php

use App\Models\Auth\User;
use Illuminate\Support\Facades\Notification;

it('sends a reset link for existing users', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->postJson('/api/auth/forgot-password', [
        'email' => $user->email,
    ])
        ->assertOk()
        ->assertJsonPath('message_code', 'auth.reset_link.sent');
});

it('returns 400 when no account matches forgot-password email', function () {
    $this->postJson('/api/auth/forgot-password', [
        'email' => 'unknown-user@example.test',
    ])
        ->assertStatus(400)
        ->assertJsonPath('message_code', 'auth.reset_link.failed');
});

it('validates forgot-password payload', function () {
    $this->postJson('/api/auth/forgot-password', [
        'email' => 'not-an-email',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message_code', 'validation.invalid');
});
