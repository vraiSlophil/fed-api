<?php

use App\Models\Auth\User;
use App\Models\Invitations\Invitation;
use App\Models\Playgrounds\Playground;
use App\Models\Themes\Theme;

function createInvitationCrudContext(array $invitationOverrides = []): array
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
                'can_update_theme' => false,
                'can_add_task' => false,
                'can_edit_task' => false,
                'can_delete_task' => false,
                'can_validate_task' => false,
            ],
        ],
        'status' => 'pending',
        'expires_at' => now()->addDays(3),
    ], $invitationOverrides));

    return compact('owner', 'theme', 'invitee', 'invitation');
}

it('returns invitation details for inviter', function () {
    $ctx = createInvitationCrudContext();
    actingAsAccessUser($ctx['owner']);

    $this->getJson("/api/invitations/{$ctx['invitation']->invitation_id}")
        ->assertOk()
        ->assertJsonPath('message_code', 'invitation.show.success')
        ->assertJsonPath('data.invitation.invitation_id', $ctx['invitation']->invitation_id);
});

it('returns invitation details for invitee', function () {
    $ctx = createInvitationCrudContext();
    actingAsAccessUser($ctx['invitee']);

    $this->getJson("/api/invitations/{$ctx['invitation']->invitation_id}")
        ->assertOk()
        ->assertJsonPath('data.invitation.invitation_id', $ctx['invitation']->invitation_id);
});

it('forbids invitation details for unrelated users', function () {
    $ctx = createInvitationCrudContext();
    $outsider = User::factory()->create();
    actingAsAccessUser($outsider);

    $this->getJson("/api/invitations/{$ctx['invitation']->invitation_id}")
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');
});

it('allows hard delete for declined invitations', function () {
    $ctx = createInvitationCrudContext([
        'status' => 'declined',
    ]);
    actingAsAccessUser($ctx['invitee']);

    $this->deleteJson("/api/invitations/{$ctx['invitation']->invitation_id}")
        ->assertNoContent();

    expect(Invitation::query()->where('invitation_id', $ctx['invitation']->invitation_id)->exists())->toBeFalse();
});

it('allows hard delete for canceled invitations', function () {
    $ctx = createInvitationCrudContext([
        'status' => 'canceled',
    ]);
    actingAsAccessUser($ctx['owner']);

    $this->deleteJson("/api/invitations/{$ctx['invitation']->invitation_id}")
        ->assertNoContent();

    expect(Invitation::query()->where('invitation_id', $ctx['invitation']->invitation_id)->exists())->toBeFalse();
});

it('rejects hard delete for pending invitations', function () {
    $ctx = createInvitationCrudContext([
        'status' => 'pending',
    ]);
    actingAsAccessUser($ctx['owner']);

    $this->deleteJson("/api/invitations/{$ctx['invitation']->invitation_id}")
        ->assertStatus(409)
        ->assertJsonPath('message_code', 'invitation.delete_not_allowed_status');
});

it('rejects hard delete for accepted invitations', function () {
    $ctx = createInvitationCrudContext([
        'status' => 'accepted',
    ]);
    actingAsAccessUser($ctx['owner']);

    $this->deleteJson("/api/invitations/{$ctx['invitation']->invitation_id}")
        ->assertStatus(409)
        ->assertJsonPath('message_code', 'invitation.delete_not_allowed_status');
});
