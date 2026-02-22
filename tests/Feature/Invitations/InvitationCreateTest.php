<?php

use App\Models\Auth\User;
use App\Models\Invitations\Invitation;
use App\Models\Playgrounds\Playground;
use App\Models\Themes\Theme;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

it('autorise la re-invitation apres refus', function () {
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

    Sanctum::actingAs($owner, ['access']);

    $this->postJson("/api/themes/{$theme->theme_id}/members", [
        'user_id' => $invitee->user_id,
        'can_view' => true,
        'can_update_theme' => false,
        'can_add_task' => false,
        'can_edit_task' => false,
        'can_delete_task' => false,
        'can_validate_task' => false,
    ])->assertStatus(201);
});

it('refuse une double invitation pending', function () {
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

    Sanctum::actingAs($owner, ['access']);

    $payload = [
        'user_id' => $invitee->user_id,
        'can_view' => true,
        'can_update_theme' => false,
        'can_add_task' => false,
        'can_edit_task' => false,
        'can_delete_task' => false,
        'can_validate_task' => false,
    ];

    $this->postJson("/api/themes/{$theme->theme_id}/members", $payload)
        ->assertStatus(201);

    $this->postJson("/api/themes/{$theme->theme_id}/members", $payload)
        ->assertStatus(409)
        ->assertJsonPath('message_code', 'theme.invitation.already_exists');
});

it('refuse de creer une invitation sans authentification', function () {
    $owner = User::factory()->create();
    $ownerPlayground = Playground::where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $invitee = User::factory()->create();

    $this->postJson("/api/themes/{$theme->theme_id}/members", [
        'user_id' => $invitee->user_id,
        'can_view' => true,
        'can_update_theme' => false,
        'can_add_task' => false,
        'can_edit_task' => false,
        'can_delete_task' => false,
        'can_validate_task' => false,
    ])->assertStatus(401)
        ->assertJsonPath('message_code', 'auth.failed');
});

it('refuse d inviter le proprietaire du theme', function () {
    $owner = User::factory()->create();
    $ownerPlayground = Playground::where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    Sanctum::actingAs($owner, ['access']);

    $this->postJson("/api/themes/{$theme->theme_id}/members", [
        'user_id' => $owner->user_id,
        'can_view' => true,
        'can_update_theme' => false,
        'can_add_task' => false,
        'can_edit_task' => false,
        'can_delete_task' => false,
        'can_validate_task' => false,
    ])->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');
});

it('refuse d inviter un utilisateur deja membre du theme', function () {
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

    Sanctum::actingAs($owner, ['access']);

    $this->postJson("/api/themes/{$theme->theme_id}/members", [
        'user_id' => $invitee->user_id,
        'can_view' => true,
        'can_update_theme' => false,
        'can_add_task' => false,
        'can_edit_task' => false,
        'can_delete_task' => false,
        'can_validate_task' => false,
    ])->assertStatus(409)
        ->assertJsonPath('message_code', 'theme.member.already_exists');
});
