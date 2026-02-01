<?php

use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

it('verifie un email via lien signe (POST)', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ],
        false
    );

    $this->postJson($url)
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'auth.verification.success');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('refuse un lien de verification sans signature', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $this->postJson('/api/email-verifications', [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ])->assertStatus(403);
});

it('refuse un lien de verification expire', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->subMinutes(10),
        [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ],
        false
    );

    $this->postJson($url)->assertStatus(403);
});

it('refuse un lien de verification avec hash invalide', function () {
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

it('renvoie une notification de verification par email', function () {
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

it('refuse la notification de verification sans auth', function () {
    $this->postJson('/api/email-verification-notifications')
        ->assertStatus(401)
        ->assertJsonPath('message_code', 'auth.failed');
});
