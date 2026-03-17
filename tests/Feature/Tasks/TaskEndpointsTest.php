<?php

use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;
use App\Models\Tasks\Task;
use App\Models\Themes\Theme;
use App\Models\Themes\ThemeUserPermission;

function createThemeForTaskEndpoints(User $owner): Theme
{
    $ownerPlayground = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    return Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);
}

function createTaskForTaskEndpoints(Theme $theme, User $author, array $overrides = []): Task
{
    return Task::factory()->create(array_merge([
        'theme_id' => $theme->theme_id,
        'user_id' => $author->user_id,
        'title' => 'Task baseline',
        'status' => 'todo',
        'archived_at' => null,
    ], $overrides));
}

it('creates tasks and lists them through task index', function () {
    $owner = User::factory()->create();
    $theme = createThemeForTaskEndpoints($owner);

    actingAsAccessUser($owner);

    $storeResponse = $this->postJson('/api/tasks', [
        'theme_id' => $theme->theme_id,
        'title' => 'Prepare launch',
        'status' => 'todo',
    ]);

    $storeResponse->assertCreated()
        ->assertJsonPath('message_code', 'task.created');

    $taskId = (string) $storeResponse->json('data.task_id');

    $this->getJson('/api/tasks?theme_id='.$theme->theme_id.'&page=1&per_page=15')
        ->assertOk()
        ->assertJsonPath('message_code', 'task.list')
        ->assertJsonFragment(['task_id' => $taskId]);
});

it('forbids task creation when user cannot add task in the theme', function () {
    $owner = User::factory()->create();
    $theme = createThemeForTaskEndpoints($owner);

    $member = User::factory()->create();
    $memberPlayground = Playground::query()
        ->where('user_id', $member->user_id)
        ->where('is_default', true)
        ->firstOrFail();

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

    actingAsAccessUser($member);

    $this->postJson('/api/tasks', [
        'theme_id' => $theme->theme_id,
        'title' => 'Forbidden create',
    ])
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');
});

it('returns 404 when filtering task list on an inaccessible theme', function () {
    $owner = User::factory()->create();
    $theme = createThemeForTaskEndpoints($owner);

    $outsider = User::factory()->create();
    actingAsAccessUser($outsider);

    $this->getJson('/api/tasks?theme_id='.$theme->theme_id)
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');
});

it('requires authentication for task index and store', function () {
    $owner = User::factory()->create();
    $theme = createThemeForTaskEndpoints($owner);

    $this->getJson('/api/tasks')
        ->assertUnauthorized()
        ->assertJsonPath('message_code', 'auth.failed');

    $this->postJson('/api/tasks', [
        'theme_id' => $theme->theme_id,
        'title' => 'No auth',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('message_code', 'auth.failed');
});

it('shows updates and deletes a task for the owner', function () {
    $owner = User::factory()->create();
    $theme = createThemeForTaskEndpoints($owner);
    $task = createTaskForTaskEndpoints($theme, $owner);

    actingAsAccessUser($owner);

    $this->getJson("/api/tasks/{$task->task_id}")
        ->assertOk()
        ->assertJsonPath('message_code', 'task.show')
        ->assertJsonPath('data.task_id', $task->task_id);

    $this->patchJson("/api/tasks/{$task->task_id}", [
        'title' => 'Task updated by owner',
    ])
        ->assertOk()
        ->assertJsonPath('message_code', 'task.updated')
        ->assertJsonPath('data.title', 'Task updated by owner');

    $this->deleteJson("/api/tasks/{$task->task_id}")
        ->assertNoContent();

    expect(Task::query()->where('task_id', $task->task_id)->exists())->toBeFalse();
});

it('forbids outsider access to task show update and delete', function () {
    $owner = User::factory()->create();
    $theme = createThemeForTaskEndpoints($owner);
    $task = createTaskForTaskEndpoints($theme, $owner);

    $outsider = User::factory()->create();

    actingAsAccessUser($outsider);

    $this->getJson("/api/tasks/{$task->task_id}")
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');

    $this->patchJson("/api/tasks/{$task->task_id}", [
        'title' => 'No permission',
    ])
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');

    $this->deleteJson("/api/tasks/{$task->task_id}")
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');
});

it('allows member with task permissions to show update and delete a task', function () {
    $owner = User::factory()->create();
    $theme = createThemeForTaskEndpoints($owner);
    $task = createTaskForTaskEndpoints($theme, $owner);

    $member = User::factory()->create();
    $memberPlayground = Playground::query()
        ->where('user_id', $member->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    ThemeUserPermission::factory()->create([
        'theme_id' => $theme->theme_id,
        'user_id' => $member->user_id,
        'target_playground_id' => $memberPlayground->playground_id,
        'can_view' => true,
        'can_update_theme' => false,
        'can_add_task' => false,
        'can_edit_task' => true,
        'can_delete_task' => true,
        'can_validate_task' => false,
        'status' => 'active',
    ]);

    actingAsAccessUser($member);

    $this->getJson("/api/tasks/{$task->task_id}")
        ->assertOk()
        ->assertJsonPath('message_code', 'task.show');

    $this->patchJson("/api/tasks/{$task->task_id}", [
        'title' => 'Task updated by member',
    ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Task updated by member');

    $this->deleteJson("/api/tasks/{$task->task_id}")
        ->assertNoContent();
});

it('requires authentication for task show update and destroy', function () {
    $owner = User::factory()->create();
    $theme = createThemeForTaskEndpoints($owner);
    $task = createTaskForTaskEndpoints($theme, $owner);

    $this->getJson("/api/tasks/{$task->task_id}")
        ->assertUnauthorized()
        ->assertJsonPath('message_code', 'auth.failed');

    $this->patchJson("/api/tasks/{$task->task_id}", [
        'title' => 'No auth patch',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('message_code', 'auth.failed');

    $this->deleteJson("/api/tasks/{$task->task_id}")
        ->assertUnauthorized()
        ->assertJsonPath('message_code', 'auth.failed');
});
