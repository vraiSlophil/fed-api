<?php

use App\Models\Auth\User;
use App\Models\Invitations\Invitation;
use App\Models\Playgrounds\Playground;
use App\Models\Tasks\Task;
use App\Models\Themes\Theme;
use App\Models\Themes\ThemeUserPermission;

function createThemeContext(): array
{
    $owner = User::factory()->create();
    $ownerPlayground = Playground::query()->where('user_id', $owner->user_id)->where('is_default', true)->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $task = Task::factory()->create([
        'theme_id' => $theme->theme_id,
        'user_id' => $owner->user_id,
        'status' => 'todo',
    ]);

    $member = User::factory()->create();
    $memberPlayground = Playground::query()->where('user_id', $member->user_id)->where('is_default', true)->firstOrFail();

    ThemeUserPermission::factory()->create([
        'theme_id' => $theme->theme_id,
        'user_id' => $member->user_id,
        'target_playground_id' => $memberPlayground->playground_id,
        'can_view' => true,
        'can_update_theme' => false,
        'can_add_task' => false,
        'can_edit_task' => false,
        'can_delete_task' => false,
        'can_validate_task' => false,
        'status' => 'active',
    ]);

    return compact('owner', 'theme', 'task', 'member');
}

function createRevokedMemberContext(): array
{
    $owner = User::factory()->create();
    $ownerPlayground = Playground::query()->where('user_id', $owner->user_id)->where('is_default', true)->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $task = Task::factory()->create([
        'theme_id' => $theme->theme_id,
        'user_id' => $owner->user_id,
        'status' => 'todo',
    ]);

    $member = User::factory()->create();
    $memberPlayground = Playground::query()->where('user_id', $member->user_id)->where('is_default', true)->firstOrFail();

    ThemeUserPermission::factory()->create([
        'theme_id' => $theme->theme_id,
        'user_id' => $member->user_id,
        'target_playground_id' => $memberPlayground->playground_id,
        'can_view' => true,
        'can_update_theme' => true,
        'can_add_task' => true,
        'can_edit_task' => true,
        'can_delete_task' => true,
        'can_validate_task' => true,
        'status' => 'revoked',
    ]);

    return compact('owner', 'theme', 'task', 'member');
}

function createPendingInvitationContext(): array
{
    $owner = User::factory()->create();
    $ownerPlayground = Playground::query()->where('user_id', $owner->user_id)->where('is_default', true)->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $invitee = User::factory()->create();

    Invitation::query()->create([
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
        'expires_at' => now()->addDays(7),
    ]);

    return compact('owner', 'theme', 'invitee');
}

function createMemberOwnedTaskContext(string $memberStatus = 'active'): array
{
    $owner = User::factory()->create();
    $ownerPlayground = Playground::query()->where('user_id', $owner->user_id)->where('is_default', true)->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $member = User::factory()->create();
    $memberPlayground = Playground::query()->where('user_id', $member->user_id)->where('is_default', true)->firstOrFail();

    ThemeUserPermission::factory()->create([
        'theme_id' => $theme->theme_id,
        'user_id' => $member->user_id,
        'target_playground_id' => $memberPlayground->playground_id,
        'can_view' => true,
        'can_update_theme' => false,
        'can_add_task' => true,
        'can_edit_task' => true,
        'can_delete_task' => true,
        'can_validate_task' => true,
        'status' => $memberStatus,
    ]);

    $task = Task::factory()->create([
        'theme_id' => $theme->theme_id,
        'user_id' => $member->user_id,
        'status' => 'todo',
    ]);

    return compact('owner', 'theme', 'member', 'task', 'memberPlayground');
}

it('forbids member without can_edit_task from updating task', function () {
    $ctx = createThemeContext();

    actingAsAccessUser($ctx['member']);

    $this->patchJson("/api/tasks/{$ctx['task']->task_id}", [
        'title' => 'Updated by member',
    ])
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');
});

it('allows owner to update task', function () {
    $ctx = createThemeContext();

    actingAsAccessUser($ctx['owner']);

    $this->patchJson("/api/tasks/{$ctx['task']->task_id}", [
        'title' => 'Updated by owner',
    ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated by owner');
});

it('forbids member without can_update_theme from updating theme', function () {
    $ctx = createThemeContext();

    actingAsAccessUser($ctx['member']);

    $this->patchJson("/api/themes/{$ctx['theme']->theme_id}", [
        'title' => 'Unauthorized update',
    ])
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');
});

it('allows active member with can_view to view theme', function () {
    $ctx = createThemeContext();

    actingAsAccessUser($ctx['member']);

    $this->getJson("/api/themes/{$ctx['theme']->theme_id}")
        ->assertOk()
        ->assertJsonPath('data.theme_id', $ctx['theme']->theme_id);
});

it('requires authentication for protected theme endpoint', function () {
    $ctx = createThemeContext();

    $this->getJson("/api/themes/{$ctx['theme']->theme_id}")
        ->assertUnauthorized()
        ->assertJsonPath('message_code', 'auth.failed');
});

it('forbids revoked members from updating tasks even with full flags', function () {
    $ctx = createRevokedMemberContext();

    actingAsAccessUser($ctx['member']);

    $this->patchJson("/api/tasks/{$ctx['task']->task_id}", [
        'title' => 'Should not be allowed',
    ])
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');
});

it('forbids users with pending invitation from viewing theme before acceptance', function () {
    $ctx = createPendingInvitationContext();

    actingAsAccessUser($ctx['invitee']);

    $this->getJson("/api/themes/{$ctx['theme']->theme_id}")
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');
});

it('allows members with can_update_theme to update title', function () {
    $ctx = createThemeContext();

    ThemeUserPermission::query()
        ->where('theme_id', $ctx['theme']->theme_id)
        ->where('user_id', $ctx['member']->user_id)
        ->update(['can_update_theme' => true]);

    actingAsAccessUser($ctx['member']);

    $this->patchJson("/api/themes/{$ctx['theme']->theme_id}", [
        'title' => 'Member allowed title update',
    ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Member allowed title update');
});

it('forbids members from changing theme playground through patch', function () {
    $ctx = createThemeContext();

    ThemeUserPermission::query()
        ->where('theme_id', $ctx['theme']->theme_id)
        ->where('user_id', $ctx['member']->user_id)
        ->update(['can_update_theme' => true]);

    $memberDefaultPlayground = Playground::query()
        ->where('user_id', $ctx['member']->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    actingAsAccessUser($ctx['member']);

    $this->patchJson("/api/themes/{$ctx['theme']->theme_id}", [
        'playground_id' => $memberDefaultPlayground->playground_id,
    ])
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');
});

it('allows owners to move theme to another owned playground', function () {
    $ctx = createThemeContext();

    $newOwnerPlayground = Playground::factory()->create([
        'user_id' => $ctx['owner']->user_id,
        'is_default' => false,
    ]);

    actingAsAccessUser($ctx['owner']);

    $this->patchJson("/api/themes/{$ctx['theme']->theme_id}", [
        'playground_id' => $newOwnerPlayground->playground_id,
    ])
        ->assertOk()
        ->assertJsonPath('data.playground_id', $newOwnerPlayground->playground_id);
});

it('rejects owner theme move to a playground they do not own', function () {
    $ctx = createThemeContext();

    $otherUser = User::factory()->create();
    $otherPlayground = Playground::query()
        ->where('user_id', $otherUser->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    actingAsAccessUser($ctx['owner']);

    $this->patchJson("/api/themes/{$ctx['theme']->theme_id}", [
        'playground_id' => $otherPlayground->playground_id,
    ])
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');
});

it('forbids revoked members from viewing tasks they created in the theme', function () {
    $ctx = createMemberOwnedTaskContext('revoked');

    actingAsAccessUser($ctx['member']);

    $this->getJson("/api/tasks/{$ctx['task']->task_id}")
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');

    $this->getJson('/api/tasks?theme_id='.$ctx['theme']->theme_id)
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');
});

it('forbids removed members from viewing tasks in a former theme', function () {
    $ctx = createMemberOwnedTaskContext('active');

    ThemeUserPermission::query()
        ->where('theme_id', $ctx['theme']->theme_id)
        ->where('user_id', $ctx['member']->user_id)
        ->delete();

    actingAsAccessUser($ctx['member']);

    $this->getJson("/api/tasks/{$ctx['task']->task_id}")
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');

    $this->getJson('/api/tasks?theme_id='.$ctx['theme']->theme_id)
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');
});
