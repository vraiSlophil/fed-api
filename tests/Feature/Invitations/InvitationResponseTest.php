<?php

use App\Models\Auth\User;
use App\Models\Invitations\Invitation;
use App\Models\Playgrounds\Playground;
use App\Models\Themes\Theme;
use App\Models\Themes\ThemeUserPermission;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

function createThemeInvitationContext(array $invitationOverrides = []): array
{
    $owner = User::factory()->create();
    $ownerPlayground = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $invitee = User::factory()->create();

    $invitation = Invitation::query()->create(array_merge([
        'inviter_user_id' => $owner->user_id,
        'invitee_user_id' => $invitee->user_id,
        'invitable_type' => Theme::class,
        'invitable_id' => $theme->theme_id,
        'payload' => [
            'permissions' => [
                'can_view' => true,
                'can_update_theme' => true,
                'can_add_task' => true,
                'can_edit_task' => true,
                'can_delete_task' => true,
                'can_validate_task' => true,
            ],
        ],
        'status' => 'pending',
        'expires_at' => now()->addMinutes(60),
    ], $invitationOverrides));

    return compact('owner', 'theme', 'invitee', 'invitation');
}

function signedInvitationUrl(string $invitationId, string $status): string
{
    return URL::temporarySignedRoute(
        'invitations.respond',
        now()->addMinutes(60),
        [
            'invitation' => $invitationId,
            'status' => $status,
        ],
        false
    );
}

it('accepts an invitation for an authenticated invitee without signed params', function () {
    $ctx = createThemeInvitationContext();

    Sanctum::actingAs($ctx['invitee'], ['access']);

    $this->patchJson("/api/invitations/{$ctx['invitation']->invitation_id}", [
        'status' => 'accepted',
    ])
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'theme.invitation.accepted');

    expect(ThemeUserPermission::query()
        ->where('theme_id', $ctx['theme']->theme_id)
        ->where('user_id', $ctx['invitee']->user_id)
        ->exists())->toBeTrue();
});

it('declines an invitation for an authenticated invitee without signed params', function () {
    $ctx = createThemeInvitationContext();

    Sanctum::actingAs($ctx['invitee'], ['access']);

    $this->patchJson("/api/invitations/{$ctx['invitation']->invitation_id}", [
        'status' => 'declined',
    ])
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'theme.invitation.declined');

    expect($ctx['invitation']->fresh()->status)->toBe('declined');
});

it('accepts an invitation without authentication when signature is valid', function () {
    $ctx = createThemeInvitationContext();

    $this->patchJson(signedInvitationUrl($ctx['invitation']->invitation_id, 'accepted'))
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'theme.invitation.accepted');
});

it('rejects unauthenticated invitation response without valid signature', function () {
    $ctx = createThemeInvitationContext();

    $this->patchJson("/api/invitations/{$ctx['invitation']->invitation_id}?status=accepted")
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'signature.invalid');
});

it('uses current authenticated session over signature when account does not match invitee', function () {
    $ctx = createThemeInvitationContext();
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser, ['access']);

    $this->patchJson(signedInvitationUrl($ctx['invitation']->invitation_id, 'accepted'), [
        'status' => 'accepted',
    ])
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');
});

it('requires status in body for authenticated requests even if query has status', function () {
    $ctx = createThemeInvitationContext();
    Sanctum::actingAs($ctx['invitee'], ['access']);

    $this->patchJson("/api/invitations/{$ctx['invitation']->invitation_id}?status=accepted")
        ->assertStatus(422)
        ->assertJsonPath('message_code', 'validation.invalid');
});

it('allows inviter to cancel a pending invitation', function () {
    $ctx = createThemeInvitationContext();
    Sanctum::actingAs($ctx['owner'], ['access']);

    $this->patchJson("/api/invitations/{$ctx['invitation']->invitation_id}", [
        'status' => 'canceled',
    ])
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'theme.invitation.canceled');

    expect($ctx['invitation']->fresh()->status)->toBe('canceled');
});

it('allows admin to cancel a pending invitation', function () {
    $ctx = createThemeInvitationContext();
    $admin = User::factory()->create([
        'role_power' => 100,
    ]);
    Sanctum::actingAs($admin, ['access']);

    $this->patchJson("/api/invitations/{$ctx['invitation']->invitation_id}", [
        'status' => 'canceled',
    ])
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'theme.invitation.canceled');
});

it('rejects cancel status for unauthenticated requests even with signature', function () {
    $ctx = createThemeInvitationContext();

    $this->patchJson(signedInvitationUrl($ctx['invitation']->invitation_id, 'canceled'))
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');
});

it('rejects unauthenticated signed responses when status is provided in body', function () {
    $ctx = createThemeInvitationContext();

    $this->patchJson(signedInvitationUrl($ctx['invitation']->invitation_id, 'accepted'), [
        'status' => 'accepted',
    ])
        ->assertStatus(422)
        ->assertJsonPath('message_code', 'validation.invalid');
});

it('rejects transition when invitation is already terminal', function () {
    $ctx = createThemeInvitationContext([
        'status' => 'declined',
    ]);
    Sanctum::actingAs($ctx['invitee'], ['access']);

    $this->patchJson("/api/invitations/{$ctx['invitation']->invitation_id}", [
        'status' => 'accepted',
    ])
        ->assertStatus(409)
        ->assertJsonPath('message_code', 'invitation.invalid_transition');
});

it('rejects expired invitation transition', function () {
    $ctx = createThemeInvitationContext([
        'expires_at' => now()->subMinutes(30),
    ]);
    Sanctum::actingAs($ctx['invitee'], ['access']);

    $this->patchJson("/api/invitations/{$ctx['invitation']->invitation_id}", [
        'status' => 'accepted',
    ])
        ->assertStatus(410)
        ->assertJsonPath('message_code', 'invitation.expired');
});

it('accepts invitation with explicit target playground for authenticated invitee', function () {
    $ctx = createThemeInvitationContext();
    $customPlayground = Playground::factory()->create([
        'user_id' => $ctx['invitee']->user_id,
        'is_default' => false,
    ]);
    Sanctum::actingAs($ctx['invitee'], ['access']);

    $this->patchJson("/api/invitations/{$ctx['invitation']->invitation_id}", [
        'status' => 'accepted',
        'target_playground_id' => $customPlayground->playground_id,
    ])->assertStatus(200);

    $permission = ThemeUserPermission::query()
        ->where('theme_id', $ctx['theme']->theme_id)
        ->where('user_id', $ctx['invitee']->user_id)
        ->firstOrFail();

    expect($permission->target_playground_id)->toBe($customPlayground->playground_id);
});

it('rejects unsupported invitable type on acceptance', function () {
    $ctx = createThemeInvitationContext([
        'invitable_type' => Playground::class,
    ]);
    Sanctum::actingAs($ctx['invitee'], ['access']);

    $this->patchJson("/api/invitations/{$ctx['invitation']->invitation_id}", [
        'status' => 'accepted',
    ])
        ->assertStatus(400)
        ->assertJsonPath('message_code', 'invitation.invalid');
});
