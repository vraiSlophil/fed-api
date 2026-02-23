<?php

use App\Domain\Auth\Services\TokenService;
use App\Models\Auth\User;
use App\Models\Invitations\Invitation;
use App\Models\Playgrounds\Playground;
use App\Models\Tasks\Task;
use App\Models\Themes\Theme;
use App\Models\Themes\ThemeUserPermission;
use Laravel\Sanctum\Sanctum;

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

it('forbids member without can_edit_task from updating task', function () {
    $ctx = createThemeContext();

    Sanctum::actingAs($ctx['member'], [TokenService::ACCESS_ABILITY]);

    $this->patchJson("/api/tasks/{$ctx['task']->task_id}", [
        'title' => 'Updated by member',
    ])
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');
});

it('allows owner to update task', function () {
    $ctx = createThemeContext();

    Sanctum::actingAs($ctx['owner'], [TokenService::ACCESS_ABILITY]);

    $this->patchJson("/api/tasks/{$ctx['task']->task_id}", [
        'title' => 'Updated by owner',
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.task.title', 'Updated by owner');
});

it('forbids member without can_update_theme from updating theme', function () {
    $ctx = createThemeContext();

    Sanctum::actingAs($ctx['member'], [TokenService::ACCESS_ABILITY]);

    $this->patchJson("/api/themes/{$ctx['theme']->theme_id}", [
        'title' => 'Unauthorized update',
    ])
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');
});

it('allows active member with can_view to view theme', function () {
    $ctx = createThemeContext();

    Sanctum::actingAs($ctx['member'], [TokenService::ACCESS_ABILITY]);

    $this->getJson("/api/themes/{$ctx['theme']->theme_id}")
        ->assertStatus(200)
        ->assertJsonPath('data.theme.theme_id', $ctx['theme']->theme_id);
});

it('requires authentication for protected theme endpoint', function () {
    $ctx = createThemeContext();

    $this->getJson("/api/themes/{$ctx['theme']->theme_id}")
        ->assertStatus(401)
        ->assertJsonPath('message_code', 'auth.failed');
});

it('forbids revoked members from updating tasks even with full flags', function () {
    $ctx = createRevokedMemberContext();

    Sanctum::actingAs($ctx['member'], [TokenService::ACCESS_ABILITY]);

    $this->patchJson("/api/tasks/{$ctx['task']->task_id}", [
        'title' => 'Should not be allowed',
    ])
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');
});

it('forbids users with pending invitation from viewing theme before acceptance', function () {
    $ctx = createPendingInvitationContext();

    Sanctum::actingAs($ctx['invitee'], [TokenService::ACCESS_ABILITY]);

    $this->getJson("/api/themes/{$ctx['theme']->theme_id}")
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');
});
