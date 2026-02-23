<?php

use App\Domain\Auth\Services\TokenService;
use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;
use App\Models\Tasks\Task;
use App\Models\Themes\Theme;
use Laravel\Sanctum\Sanctum;

it('returns 204 with an empty body when deleting a task', function () {
    $user = User::factory()->create();
    $playground = Playground::query()
        ->where('user_id', $user->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $user->user_id,
        'playground_id' => $playground->playground_id,
    ]);

    $task = Task::factory()->create([
        'theme_id' => $theme->theme_id,
        'user_id' => $user->user_id,
        'status' => 'todo',
    ]);

    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $response = $this->deleteJson("/api/tasks/{$task->task_id}");

    $response->assertNoContent();
    expect($response->getContent())->toBe('');
});

it('returns 204 with an empty body when deleting a theme', function () {
    $user = User::factory()->create();
    $playground = Playground::query()
        ->where('user_id', $user->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $user->user_id,
        'playground_id' => $playground->playground_id,
    ]);

    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $response = $this->deleteJson("/api/themes/{$theme->theme_id}");

    $response->assertNoContent();
    expect($response->getContent())->toBe('');
});
