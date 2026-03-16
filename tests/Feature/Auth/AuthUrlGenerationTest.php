<?php

use App\Domain\Invitations\Services\InvitationLinkGenerator;
use App\Models\Auth\User;
use App\Models\Invitations\Invitation;
use App\Models\Playgrounds\Playground;
use App\Models\Themes\Theme;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;

it('builds password reset links from the frontend url', function () {
    $user = User::factory()->create([
        'email' => 'reset@example.test',
    ]);

    config()->set('app.frontend_url', 'https://front.example.test');

    $notification = new QueuedResetPassword('reset-token');
    $mailMessage = $notification->toMail($user);
    $url = (fn (): string => $this->actionUrl)->call($mailMessage);

    expect($url)->toBe('https://front.example.test/password-reset/reset-token?email=reset@example.test');
});

it('builds verification email links from the frontend url and configured path', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
        'email' => 'verify@example.test',
    ]);

    config()->set('app.frontend_url', 'https://front.example.test');
    config()->set('app.frontend_verify_email_path', '/verify-email');

    $notification = new QueuedVerifyEmail;
    $url = (fn (User $notifiable): string => $this->verificationUrl($notifiable))->call($notification, $user);

    expect($url)
        ->toStartWith('https://front.example.test/verify-email?')
        ->toContain('id='.$user->getKey())
        ->toContain('expires=')
        ->toContain('signature=');
});

it('builds invitation inbox links from the frontend url and configured path', function () {
    config()->set('app.frontend_url', 'https://front.example.test');
    config()->set('app.frontend_invitation_path', '/invite/{invitationId}');

    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $playground = Playground::factory()->create([
        'user_id' => $owner->user_id,
    ]);
    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $playground->playground_id,
    ]);

    $invitation = Invitation::factory()->create([
        'inviter_user_id' => $owner->user_id,
        'invitee_user_id' => $invitee->user_id,
        'invitable_type' => Theme::class,
        'invitable_id' => $theme->theme_id,
        'expires_at' => now()->addDay(),
    ]);

    $links = app(InvitationLinkGenerator::class)->buildInboxLinks($invitation);

    expect($links['accept'])
        ->toBe('https://front.example.test/invite/'.$invitation->invitation_id.'?intent=accept');

    expect($links['decline'])
        ->toBe('https://front.example.test/invite/'.$invitation->invitation_id.'?intent=decline');
});
