<?php

use App\Models\Invitation;
use App\Models\Playground;
use App\Models\Theme;
use App\Models\ThemeUserPermission;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

it('accepte une invitation via signature et auth', function () {
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

    $invitation = Invitation::create([
        'inviter_user_id' => $owner->user_id,
        'invitee_user_id' => $invitee->user_id,
        'invitable_type' => Theme::class,
        'invitable_id' => $theme->theme_id,
        'payload' => [
            'model' => 'theme',
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
    ]);

    $url = URL::temporarySignedRoute(
        'invitations.respond',
        now()->addMinutes(60),
        [
            'invitationId' => $invitation->invitation_id,
            'status' => 'accepted',
        ],
        false
    );

    Sanctum::actingAs($invitee, ['access']);

    $this->patchJson($url)
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'theme.invitation.accepted');

    $permission = ThemeUserPermission::where('theme_id', $theme->theme_id)
        ->where('user_id', $invitee->user_id)
        ->firstOrFail();

    expect($permission->status)->toBe('active');
    expect($permission->target_playground_id)->toBe($inviteePlayground->playground_id);
});

it('refuse une invitation via signature et auth', function () {
    $owner = User::factory()->create();
    $ownerPlayground = Playground::where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $invitee = User::factory()->create();

    $invitation = Invitation::create([
        'inviter_user_id' => $owner->user_id,
        'invitee_user_id' => $invitee->user_id,
        'invitable_type' => Theme::class,
        'invitable_id' => $theme->theme_id,
        'payload' => [
            'model' => 'theme',
            'permissions' => [
                'can_view' => true,
            ],
        ],
        'status' => 'pending',
        'expires_at' => now()->addMinutes(60),
    ]);

    $url = URL::temporarySignedRoute(
        'invitations.respond',
        now()->addMinutes(60),
        [
            'invitationId' => $invitation->invitation_id,
            'status' => 'declined',
        ],
        false
    );

    Sanctum::actingAs($invitee, ['access']);

    $this->patchJson($url)
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'theme.invitation.declined');

    $invitation->refresh();
    expect($invitation->status)->toBe('declined');
    expect(ThemeUserPermission::where('theme_id', $theme->theme_id)
        ->where('user_id', $invitee->user_id)
        ->exists())
        ->toBeFalse();
});
