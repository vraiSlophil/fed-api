<?php

use App\Models\Auth\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

it('verifies an email through a signed link (POST)', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        [
            'id' => $user->getKey(),
            'hash' => hash('sha256', $user->getEmailForVerification()),
        ],
        false
    );

    $this->postJson($url)
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'auth.verification.success');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects an email verification link without a signature', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $this->postJson('/api/email-verifications', [
        'id' => $user->getKey(),
        'hash' => hash('sha256', $user->getEmailForVerification()),
    ])->assertStatus(403);
});

it('rejects an expired email verification link', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->subMinutes(10),
        [
            'id' => $user->getKey(),
            'hash' => hash('sha256', $user->getEmailForVerification()),
        ],
        false
    );

    $this->postJson($url)->assertStatus(403);
});

it('rejects an email verification link with an invalid hash', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        [
            'id' => $user->getKey(),
            'hash' => 'invalid-hash',
        ],
        false
    );

    $this->postJson($url)
        ->assertStatus(400)
        ->assertJsonPath('message_code', 'auth.verification.invalid');
});

it('returns already verified when the email is already confirmed', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        [
            'id' => $user->getKey(),
            'hash' => hash('sha256', $user->getEmailForVerification()),
        ],
        false
    );

    $this->postJson($url)
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'auth.verification.already_verified');
});

it('returns not found when the verification user does not exist', function () {
    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'hash' => hash('sha256', 'ghost@example.test'),
        ],
        false
    );

    $this->postJson($url)
        ->assertStatus(404)
        ->assertJsonPath('message_code', 'resource.not_found');
});

it('sends the verification email notification', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    Sanctum::actingAs($user, ['access']);

    $this->postJson('/api/email-verification-notifications')
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'email.verification.sent');

    Notification::assertSentTo($user, QueuedVerifyEmail::class);
});

it('rejects the verification notification request without authentication', function () {
    $this->postJson('/api/email-verification-notifications')
        ->assertStatus(401)
        ->assertJsonPath('message_code', 'auth.failed');
});

it('returns already verified and does not send a notification', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($user, ['access']);

    $this->postJson('/api/email-verification-notifications')
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'email.verification.already_verified');

    Notification::assertNothingSent();
});
