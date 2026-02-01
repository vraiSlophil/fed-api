<?php

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

    $permission = ThemeUserPermission::factory()->invited()->create([
        'theme_id' => $theme->theme_id,
        'user_id' => $invitee->user_id,
    ]);

    $url = URL::temporarySignedRoute(
        'invitations.respond',
        now()->addMinutes(60),
        [
            'invitation' => $permission->permission_id,
            'status' => 'accepted',
        ],
        false
    );

    Sanctum::actingAs($invitee, ['access']);

    $this->patchJson($url)
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'theme.invitation.accepted');

    $permission->refresh();
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

    $permission = ThemeUserPermission::factory()->invited()->create([
        'theme_id' => $theme->theme_id,
        'user_id' => $invitee->user_id,
    ]);

    $url = URL::temporarySignedRoute(
        'invitations.respond',
        now()->addMinutes(60),
        [
            'invitation' => $permission->permission_id,
            'status' => 'declined',
        ],
        false
    );

    Sanctum::actingAs($invitee, ['access']);

    $this->patchJson($url)
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'theme.invitation.declined');

    expect(ThemeUserPermission::where('permission_id', $permission->permission_id)->exists())
        ->toBeFalse();
});
