<?php

use App\Models\Auth\User;
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

it('retourne deja verifie si l email est deja confirme', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
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
        ->assertJsonPath('message_code', 'auth.verification.already_verified');
});

it('retourne not found si l utilisateur de verification n existe pas', function () {
    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'hash' => sha1('ghost@example.test'),
        ],
        false
    );

    $this->postJson($url)
        ->assertStatus(404)
        ->assertJsonPath('message_code', 'resource.not_found');
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

it('retourne already verified et n envoie pas de notification', function () {
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
