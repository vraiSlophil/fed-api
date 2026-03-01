<?php

use App\Models\Auth\User;
use App\Models\Invitations\Invitation;
use App\Models\Playgrounds\Playground;
use App\Models\Themes\Theme;
use Laravel\Sanctum\Sanctum;

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
    Sanctum::actingAs($ctx['owner'], ['access']);

    $this->getJson("/api/invitations/{$ctx['invitation']->invitation_id}")
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'invitation.show.success')
        ->assertJsonPath('data.invitation.invitation_id', $ctx['invitation']->invitation_id);
});

it('returns invitation details for invitee', function () {
    $ctx = createInvitationCrudContext();
    Sanctum::actingAs($ctx['invitee'], ['access']);

    $this->getJson("/api/invitations/{$ctx['invitation']->invitation_id}")
        ->assertStatus(200)
        ->assertJsonPath('data.invitation.invitation_id', $ctx['invitation']->invitation_id);
});

it('forbids invitation details for unrelated users', function () {
    $ctx = createInvitationCrudContext();
    $outsider = User::factory()->create();
    Sanctum::actingAs($outsider, ['access']);

    $this->getJson("/api/invitations/{$ctx['invitation']->invitation_id}")
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');
});

it('allows hard delete for declined invitations', function () {
    $ctx = createInvitationCrudContext([
        'status' => 'declined',
    ]);
    Sanctum::actingAs($ctx['invitee'], ['access']);

    $this->deleteJson("/api/invitations/{$ctx['invitation']->invitation_id}")
        ->assertStatus(204);

    expect(Invitation::query()->where('invitation_id', $ctx['invitation']->invitation_id)->exists())->toBeFalse();
});

it('allows hard delete for canceled invitations', function () {
    $ctx = createInvitationCrudContext([
        'status' => 'canceled',
    ]);
    Sanctum::actingAs($ctx['owner'], ['access']);

    $this->deleteJson("/api/invitations/{$ctx['invitation']->invitation_id}")
        ->assertStatus(204);

    expect(Invitation::query()->where('invitation_id', $ctx['invitation']->invitation_id)->exists())->toBeFalse();
});

it('rejects hard delete for pending invitations', function () {
    $ctx = createInvitationCrudContext([
        'status' => 'pending',
    ]);
    Sanctum::actingAs($ctx['owner'], ['access']);

    $this->deleteJson("/api/invitations/{$ctx['invitation']->invitation_id}")
        ->assertStatus(409)
        ->assertJsonPath('message_code', 'invitation.delete_not_allowed_status');
});

it('rejects hard delete for accepted invitations', function () {
    $ctx = createInvitationCrudContext([
        'status' => 'accepted',
    ]);
    Sanctum::actingAs($ctx['owner'], ['access']);

    $this->deleteJson("/api/invitations/{$ctx['invitation']->invitation_id}")
        ->assertStatus(409)
        ->assertJsonPath('message_code', 'invitation.delete_not_allowed_status');
});
