<?php

use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;
use App\Models\Tasks\Task;
use App\Models\Themes\Theme;

it('accepts canonical in_progress task status and persists it', function () {
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

    actingAsAccessUser($owner);

    $this->patchJson("/api/tasks/{$task->task_id}", [
        'status' => 'in_progress',
    ])
        ->assertOk()
        ->assertJsonPath('data.task.status', 'in_progress');

    expect($task->fresh()->status->value)->toBe('in_progress');
});

it('rejects non canonical task status', function () {
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

    actingAsAccessUser($owner);

    $this->patchJson("/api/tasks/{$task->task_id}", [
        'status' => 'progressing',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message_code', 'validation.invalid');
});
