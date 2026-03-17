<?php

use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;
use App\Models\Tasks\Task;
use App\Models\Themes\Theme;
use App\Models\Themes\ThemeUserPermission;

function createOwnedThemeAndTask(): array
{
    $owner = User::factory()->create();

    $ownerDefaultPlayground = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerDefaultPlayground->playground_id,
    ]);

    $task = Task::factory()->create([
        'theme_id' => $theme->theme_id,
        'user_id' => $owner->user_id,
        'status' => 'todo',
        'archived_at' => null,
    ]);

    return [
        'owner' => $owner,
        'theme' => $theme,
        'task' => $task,
        'owner_default_playground' => $ownerDefaultPlayground,
    ];
}

it('updates task archived_at through PATCH and removes action verb task routes', function () {
    $ctx = createOwnedThemeAndTask();

    actingAsAccessUser($ctx['owner']);

    $archiveResponse = $this->patchJson("/api/tasks/{$ctx['task']->task_id}", [
        'archived_at' => now()->toISOString(),
    ]);

    $archiveResponse->assertOk();
    expect($archiveResponse->json('data.archived_at'))->not->toBeNull();

    $restoreResponse = $this->patchJson("/api/tasks/{$ctx['task']->task_id}", [
        'archived_at' => null,
    ]);

    $restoreResponse->assertOk();
    expect($restoreResponse->json('data.archived_at'))->toBeNull();

    $this->postJson("/api/tasks/{$ctx['task']->task_id}/archive")
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');
    $this->postJson("/api/tasks/{$ctx['task']->task_id}/restore")
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');
    $this->postJson("/api/tasks/{$ctx['task']->task_id}/complete")
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');
    $this->postJson("/api/tasks/{$ctx['task']->task_id}/uncomplete")
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');
});

it('allows validator-only members to transition done status but not edit fields', function () {
    $ctx = createOwnedThemeAndTask();

    $validator = User::factory()->create();

    $validatorDefaultPlayground = Playground::query()
        ->where('user_id', $validator->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    ThemeUserPermission::factory()->create([
        'theme_id' => $ctx['theme']->theme_id,
        'user_id' => $validator->user_id,
        'target_playground_id' => $validatorDefaultPlayground->playground_id,
        'can_view' => true,
        'can_update_theme' => false,
        'can_add_task' => false,
        'can_edit_task' => false,
        'can_delete_task' => false,
        'can_validate_task' => true,
        'status' => 'active',
    ]);

    actingAsAccessUser($validator);

    $this->patchJson("/api/tasks/{$ctx['task']->task_id}", [
        'status' => 'done',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'done');

    $this->patchJson("/api/tasks/{$ctx['task']->task_id}", [
        'title' => 'validator-cannot-edit-title',
    ])
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');
});

it('sets default playground through PATCH and removes set-default action route', function () {
    $user = User::factory()->create();

    $currentDefault = Playground::query()
        ->where('user_id', $user->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $secondary = Playground::factory()->create([
        'user_id' => $user->user_id,
        'is_default' => false,
    ]);

    actingAsAccessUser($user);

    $this->patchJson("/api/playgrounds/{$secondary->playground_id}", [
        'is_default' => true,
    ])
        ->assertOk()
        ->assertJsonPath('message_code', 'playground.update.success');

    expect($secondary->fresh()->is_default)->toBeTrue();
    expect($currentDefault->fresh()->is_default)->toBeFalse();

    $this->postJson("/api/playgrounds/{$secondary->playground_id}/set-default")
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');
});

it('removes non-crud playground routes', function () {
    $user = User::factory()->create();

    $playground = Playground::factory()->create([
        'user_id' => $user->user_id,
        'slug' => 'legacy-playground-route',
        'is_default' => false,
    ]);

    actingAsAccessUser($user);

    $this->getJson("/api/playgrounds/{$playground->playground_id}/themes")
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');

    $this->getJson("/api/playgrounds/{$playground->playground_id}/stats")
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');

    $this->getJson('/api/playgrounds/by-slug/legacy-playground-route')
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');
});

it('updates member status and target playground through PATCH and removes action routes', function () {
    $ctx = createOwnedThemeAndTask();

    $member = User::factory()->create();

    $memberDefaultPlayground = Playground::query()
        ->where('user_id', $member->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    ThemeUserPermission::factory()->create([
        'theme_id' => $ctx['theme']->theme_id,
        'user_id' => $member->user_id,
        'target_playground_id' => $memberDefaultPlayground->playground_id,
        'can_view' => true,
        'can_update_theme' => false,
        'can_add_task' => true,
        'can_edit_task' => true,
        'can_delete_task' => false,
        'can_validate_task' => false,
        'status' => 'active',
    ]);

    actingAsAccessUser($ctx['owner']);

    $this->patchJson("/api/themes/{$ctx['theme']->theme_id}/members/{$member->user_id}", [
        'status' => 'revoked',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'revoked');

    $this->patchJson("/api/themes/{$ctx['theme']->theme_id}/members/{$member->user_id}", [
        'status' => 'active',
    ])->assertOk();

    actingAsAccessUser($member);

    $newTargetPlayground = Playground::factory()->create([
        'user_id' => $member->user_id,
        'is_default' => false,
    ]);

    $this->patchJson("/api/themes/{$ctx['theme']->theme_id}/members/{$member->user_id}", [
        'target_playground_id' => $newTargetPlayground->playground_id,
    ])
        ->assertOk()
        ->assertJsonPath('message_code', 'theme.move.success');

    $this->postJson("/api/themes/{$ctx['theme']->theme_id}/members/{$member->user_id}/deactivate")
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');
    $this->postJson("/api/themes/{$ctx['theme']->theme_id}/members/{$member->user_id}/reactivate")
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');
    $this->patchJson("/api/themes/{$ctx['theme']->theme_id}/members/{$member->user_id}/move-to-playground", [
        'target_playground_id' => $newTargetPlayground->playground_id,
    ])
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');
    $this->postJson("/api/themes/{$ctx['theme']->theme_id}/leave")
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');

    $this->deleteJson("/api/themes/{$ctx['theme']->theme_id}/members/{$member->user_id}")
        ->assertOk()
        ->assertJsonPath('message_code', 'theme.member.left');

    expect(ThemeUserPermission::query()
        ->where('theme_id', $ctx['theme']->theme_id)
        ->where('user_id', $member->user_id)
        ->exists())->toBeFalse();
});

it('rejects incoherent member permission graph on PATCH /api/themes/{theme}/members/{userId}', function () {
    $ctx = createOwnedThemeAndTask();

    $member = User::factory()->create();

    $memberDefaultPlayground = Playground::query()
        ->where('user_id', $member->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    ThemeUserPermission::factory()->create([
        'theme_id' => $ctx['theme']->theme_id,
        'user_id' => $member->user_id,
        'target_playground_id' => $memberDefaultPlayground->playground_id,
        'can_view' => true,
        'can_update_theme' => false,
        'can_add_task' => false,
        'can_edit_task' => false,
        'can_delete_task' => false,
        'can_validate_task' => false,
        'status' => 'active',
    ]);

    actingAsAccessUser($ctx['owner']);

    $this->patchJson("/api/themes/{$ctx['theme']->theme_id}/members/{$member->user_id}", [
        'can_view' => false,
        'can_edit_task' => true,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message_code', 'theme.permissions.invalid');
});
