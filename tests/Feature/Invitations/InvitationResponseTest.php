<?php

use App\Models\Invitation;
use App\Models\Playground;
use App\Models\Theme;
use App\Models\ThemeUserPermission;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

it('accepte une invitation via signature et auth', function () {
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

    $invitation = Invitation::create([
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

    $invitation = Invitation::create([
        'inviter_user_id' => $owner->user_id,
        'invitee_user_id' => $invitee->user_id,
        'invitable_type' => Theme::class,
        'invitable_id' => $theme->theme_id,
        'payload' => [
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

it('rejette une invitation sans authentification', function () {
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
            'status' => 'accepted',
        ],
        false
    );

    $this->patchJson($url)
        ->assertStatus(401)
        ->assertJsonPath('message_code', 'auth.failed');
});

it('rejette une invitation avec signature invalide', function () {
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
            'permissions' => [
                'can_view' => true,
            ],
        ],
        'status' => 'pending',
        'expires_at' => now()->addMinutes(60),
    ]);

    Sanctum::actingAs($invitee, ['access']);

    $this->patchJson("/api/invitations/{$invitation->invitation_id}?status=accepted")
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'signature.invalid');
});

it('rejette une invitation pour un utilisateur different', function () {
    $owner = User::factory()->create();
    $ownerPlayground = Playground::where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $invitee = User::factory()->create();
    $otherUser = User::factory()->create();

    $invitation = Invitation::create([
        'inviter_user_id' => $owner->user_id,
        'invitee_user_id' => $invitee->user_id,
        'invitable_type' => Theme::class,
        'invitable_id' => $theme->theme_id,
        'payload' => [
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
            'status' => 'accepted',
        ],
        false
    );

    Sanctum::actingAs($otherUser, ['access']);

    $this->patchJson($url)
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');
});

it('rejette une invitation inexistante', function () {
    $invitee = User::factory()->create();

    $url = URL::temporarySignedRoute(
        'invitations.respond',
        now()->addMinutes(60),
        [
            'invitationId' => (string) Str::uuid(),
            'status' => 'accepted',
        ],
        false
    );

    Sanctum::actingAs($invitee, ['access']);

    $this->patchJson($url)
        ->assertStatus(404)
        ->assertJsonPath('message_code', 'resource.not_found');
});

it('rejette une invitation deja traitee', function () {
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
            'permissions' => [
                'can_view' => true,
            ],
        ],
        'status' => 'accepted',
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
        ->assertStatus(409)
        ->assertJsonPath('message_code', 'invitation.already_responded');
});

it('rejette une invitation expiree meme avec signature valide', function () {
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

    $invitation = Invitation::create([
        'inviter_user_id' => $owner->user_id,
        'invitee_user_id' => $invitee->user_id,
        'invitable_type' => Theme::class,
        'invitable_id' => $theme->theme_id,
        'payload' => [
            'permissions' => [
                'can_view' => true,
            ],
        ],
        'status' => 'pending',
        'expires_at' => now()->subMinutes(10),
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
        ->assertStatus(410)
        ->assertJsonPath('message_code', 'invitation.expired');
});

it('rejette une invitation si status est manquant', function () {
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
        'payload' => ['permissions' => ['can_view' => true]],
        'status' => 'pending',
        'expires_at' => now()->addMinutes(60),
    ]);

    $url = URL::temporarySignedRoute(
        'invitations.respond',
        now()->addMinutes(60),
        ['invitationId' => $invitation->invitation_id],
        false
    );

    Sanctum::actingAs($invitee, ['access']);

    $this->patchJson($url)
        ->assertStatus(422)
        ->assertJsonPath('message_code', 'validation.invalid');
});

it('rejette une invitation si status est invalide', function () {
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
        'payload' => ['permissions' => ['can_view' => true]],
        'status' => 'pending',
        'expires_at' => now()->addMinutes(60),
    ]);

    $url = URL::temporarySignedRoute(
        'invitations.respond',
        now()->addMinutes(60),
        [
            'invitationId' => $invitation->invitation_id,
            'status' => 'wrong-status',
        ],
        false
    );

    Sanctum::actingAs($invitee, ['access']);

    $this->patchJson($url)
        ->assertStatus(422)
        ->assertJsonPath('message_code', 'validation.invalid');
});

it('accepte une invitation avec target playground explicite', function () {
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
    $customPlayground = Playground::factory()->create([
        'user_id' => $invitee->user_id,
        'is_default' => false,
    ]);

    $invitation = Invitation::create([
        'inviter_user_id' => $owner->user_id,
        'invitee_user_id' => $invitee->user_id,
        'invitable_type' => Theme::class,
        'invitable_id' => $theme->theme_id,
        'payload' => ['permissions' => ['can_view' => true]],
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

    $this->patchJson($url, ['target_playground_id' => $customPlayground->playground_id])
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'theme.invitation.accepted');

    $permission = ThemeUserPermission::where('theme_id', $theme->theme_id)
        ->where('user_id', $invitee->user_id)
        ->firstOrFail();

    expect($permission->target_playground_id)->toBe($customPlayground->playground_id);
});

it('rejette target playground qui ne appartient pas a invite', function () {
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
    $otherUser = User::factory()->create();
    $foreignPlayground = Playground::factory()->create([
        'user_id' => $otherUser->user_id,
        'is_default' => false,
    ]);

    $invitation = Invitation::create([
        'inviter_user_id' => $owner->user_id,
        'invitee_user_id' => $invitee->user_id,
        'invitable_type' => Theme::class,
        'invitable_id' => $theme->theme_id,
        'payload' => ['permissions' => ['can_view' => true]],
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

    $this->patchJson($url, ['target_playground_id' => $foreignPlayground->playground_id])
        ->assertStatus(404)
        ->assertJsonPath('message_code', 'resource.not_found');
});

it('rejette une invitation avec invitable non supporte', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $playground = Playground::where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $invitation = Invitation::create([
        'inviter_user_id' => $owner->user_id,
        'invitee_user_id' => $invitee->user_id,
        'invitable_type' => Playground::class,
        'invitable_id' => $playground->playground_id,
        'payload' => ['permissions' => ['can_view' => true]],
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
        ->assertStatus(400)
        ->assertJsonPath('message_code', 'invitation.invalid');
});
