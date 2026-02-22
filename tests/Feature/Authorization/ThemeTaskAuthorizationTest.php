<?php

use App\Domain\Auth\Services\TokenService;
use App\Models\Auth\User;
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
