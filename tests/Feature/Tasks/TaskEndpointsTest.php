<?php

use App\Domain\Auth\Services\TokenService;
use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;
use App\Models\Tasks\Task;
use App\Models\Themes\Theme;
use App\Models\Themes\ThemeUserPermission;
use Laravel\Sanctum\Sanctum;

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
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $theme = createThemeForTaskEndpoints($owner);

    Sanctum::actingAs($owner, [TokenService::ACCESS_ABILITY]);

    $storeResponse = $this->postJson('/api/tasks', [
        'theme_id' => $theme->theme_id,
        'title' => 'Prepare launch',
        'status' => 'todo',
    ]);

    $storeResponse->assertStatus(201)
        ->assertJsonPath('message_code', 'task.created');

    $taskId = (string) $storeResponse->json('data.task.task_id');

    $this->getJson('/api/tasks?theme_id='.$theme->theme_id.'&page=1&per_page=15')
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'task.list')
        ->assertJsonFragment(['task_id' => $taskId]);
});

it('forbids task creation when user cannot add task in the theme', function () {
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $theme = createThemeForTaskEndpoints($owner);

    $member = User::factory()->create([
        'email_verified_at' => now(),
    ]);
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

    Sanctum::actingAs($member, [TokenService::ACCESS_ABILITY]);

    $this->postJson('/api/tasks', [
        'theme_id' => $theme->theme_id,
        'title' => 'Forbidden create',
    ])
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');
});

it('returns 404 when filtering task list on an inaccessible theme', function () {
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $theme = createThemeForTaskEndpoints($owner);

    $outsider = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    Sanctum::actingAs($outsider, [TokenService::ACCESS_ABILITY]);

    $this->getJson('/api/tasks?theme_id='.$theme->theme_id)
        ->assertStatus(404)
        ->assertJsonPath('message_code', 'resource.not_found');
});

it('requires authentication for task index and store', function () {
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $theme = createThemeForTaskEndpoints($owner);

    $this->getJson('/api/tasks')
        ->assertStatus(401)
        ->assertJsonPath('message_code', 'auth.failed');

    $this->postJson('/api/tasks', [
        'theme_id' => $theme->theme_id,
        'title' => 'No auth',
    ])
        ->assertStatus(401)
        ->assertJsonPath('message_code', 'auth.failed');
});

it('shows updates and deletes a task for the owner', function () {
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $theme = createThemeForTaskEndpoints($owner);
    $task = createTaskForTaskEndpoints($theme, $owner);

    Sanctum::actingAs($owner, [TokenService::ACCESS_ABILITY]);

    $this->getJson("/api/tasks/{$task->task_id}")
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'task.show')
        ->assertJsonPath('data.task.task_id', $task->task_id);

    $this->patchJson("/api/tasks/{$task->task_id}", [
        'title' => 'Task updated by owner',
    ])
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'task.updated')
        ->assertJsonPath('data.task.title', 'Task updated by owner');

    $this->deleteJson("/api/tasks/{$task->task_id}")
        ->assertNoContent();

    expect(Task::query()->where('task_id', $task->task_id)->exists())->toBeFalse();
});

it('forbids outsider access to task show update and delete', function () {
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $theme = createThemeForTaskEndpoints($owner);
    $task = createTaskForTaskEndpoints($theme, $owner);

    $outsider = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($outsider, [TokenService::ACCESS_ABILITY]);

    $this->getJson("/api/tasks/{$task->task_id}")
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');

    $this->patchJson("/api/tasks/{$task->task_id}", [
        'title' => 'No permission',
    ])
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');

    $this->deleteJson("/api/tasks/{$task->task_id}")
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');
});

it('allows member with task permissions to show update and delete a task', function () {
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $theme = createThemeForTaskEndpoints($owner);
    $task = createTaskForTaskEndpoints($theme, $owner);

    $member = User::factory()->create([
        'email_verified_at' => now(),
    ]);
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

    Sanctum::actingAs($member, [TokenService::ACCESS_ABILITY]);

    $this->getJson("/api/tasks/{$task->task_id}")
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'task.show');

    $this->patchJson("/api/tasks/{$task->task_id}", [
        'title' => 'Task updated by member',
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.task.title', 'Task updated by member');

    $this->deleteJson("/api/tasks/{$task->task_id}")
        ->assertNoContent();
});

it('requires authentication for task show update and destroy', function () {
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $theme = createThemeForTaskEndpoints($owner);
    $task = createTaskForTaskEndpoints($theme, $owner);

    $this->getJson("/api/tasks/{$task->task_id}")
        ->assertStatus(401)
        ->assertJsonPath('message_code', 'auth.failed');

    $this->patchJson("/api/tasks/{$task->task_id}", [
        'title' => 'No auth patch',
    ])
        ->assertStatus(401)
        ->assertJsonPath('message_code', 'auth.failed');

    $this->deleteJson("/api/tasks/{$task->task_id}")
        ->assertStatus(401)
        ->assertJsonPath('message_code', 'auth.failed');
});
