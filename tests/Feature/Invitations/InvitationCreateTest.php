<?php

use App\Models\Auth\User;
use App\Models\Invitations\Invitation;
use App\Models\Playgrounds\Playground;
use App\Models\Themes\Theme;
use Illuminate\Support\Facades\Mail;

function invitationPayload(Theme $theme, string $inviteeUserId): array
{
    return [
        'invitee_user_id' => $inviteeUserId,
        'invitable_type' => 'theme',
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
    ];
}

it('allows re-invitation after a decline', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $ownerPlayground = Playground::where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $invitee = User::factory()->create();

    Invitation::create([
        'inviter_user_id' => $owner->user_id,
        'invitee_user_id' => $invitee->user_id,
        'invitable_type' => Theme::class,
        'invitable_id' => $theme->theme_id,
        'payload' => [
            'permissions' => ['can_view' => true],
        ],
        'status' => 'declined',
        'expires_at' => now()->addDays(2),
    ]);

    actingAsAccessUser($owner);

    $this->postJson('/api/invitations', invitationPayload($theme, $invitee->user_id))
        ->assertCreated();
});

it('rejects a duplicate pending invitation', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $ownerPlayground = Playground::where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $invitee = User::factory()->create();

    actingAsAccessUser($owner);

    $payload = invitationPayload($theme, $invitee->user_id);

    $this->postJson('/api/invitations', $payload)
        ->assertCreated();

    $this->postJson('/api/invitations', $payload)
        ->assertStatus(409)
        ->assertJsonPath('message_code', 'theme.invitation.already_exists');
});

it('rejects invitation creation without authentication', function () {
    $owner = User::factory()->create();
    $ownerPlayground = Playground::where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $invitee = User::factory()->create();

    $this->postJson('/api/invitations', invitationPayload($theme, $invitee->user_id))
        ->assertUnauthorized()
        ->assertJsonPath('message_code', 'auth.failed');
});

it('rejects inviting the theme owner', function () {
    $owner = User::factory()->create();
    $ownerPlayground = Playground::where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    actingAsAccessUser($owner);

    $this->postJson('/api/invitations', invitationPayload($theme, $owner->user_id))
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');
});

it('rejects invitation payload with action permissions when can_view is false', function () {
    $owner = User::factory()->create();
    $ownerPlayground = Playground::where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $invitee = User::factory()->create();
    actingAsAccessUser($owner);

    $payload = invitationPayload($theme, $invitee->user_id);
    $payload['payload']['permissions']['can_view'] = false;
    $payload['payload']['permissions']['can_edit_task'] = true;

    $this->postJson('/api/invitations', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('message_code', 'theme.permissions.invalid');
});

it('rejects inviting a user who is already a theme member', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $ownerPlayground = Playground::where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $invitee = User::factory()->create();
    $inviteePlayground = Playground::where('user_id', $invitee->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    \App\Models\Themes\ThemeUserPermission::create([
        'theme_id' => $theme->theme_id,
        'user_id' => $invitee->user_id,
        'target_playground_id' => $inviteePlayground->playground_id,
        'can_view' => true,
        'can_update_theme' => false,
        'can_add_task' => false,
        'can_edit_task' => false,
        'can_delete_task' => false,
        'can_validate_task' => false,
        'status' => 'active',
    ]);

    actingAsAccessUser($owner);

    $this->postJson('/api/invitations', invitationPayload($theme, $invitee->user_id))
        ->assertStatus(409)
        ->assertJsonPath('message_code', 'theme.member.already_exists');
});

it('returns 404 on removed POST /api/themes/{theme}/members invitation endpoint', function () {
    $owner = User::factory()->create();
    $ownerPlayground = Playground::where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();
    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);
    $invitee = User::factory()->create();

    actingAsAccessUser($owner);

    $this->postJson("/api/themes/{$theme->theme_id}/members", [
        'user_id' => $invitee->user_id,
    ])->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');
});
