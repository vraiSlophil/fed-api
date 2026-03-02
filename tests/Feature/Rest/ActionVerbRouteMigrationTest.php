<?php

use App\Domain\Auth\Services\TokenService;
use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;
use App\Models\Tasks\Task;
use App\Models\Themes\Theme;
use App\Models\Themes\ThemeUserPermission;
use Laravel\Sanctum\Sanctum;

function createOwnedThemeAndTask(): array
{
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);

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

    Sanctum::actingAs($ctx['owner'], [TokenService::ACCESS_ABILITY]);

    $archiveResponse = $this->patchJson("/api/tasks/{$ctx['task']->task_id}", [
        'archived_at' => now()->toISOString(),
    ]);

    $archiveResponse->assertStatus(200);
    expect($archiveResponse->json('data.task.archived_at'))->not->toBeNull();

    $restoreResponse = $this->patchJson("/api/tasks/{$ctx['task']->task_id}", [
        'archived_at' => null,
    ]);

    $restoreResponse->assertStatus(200);
    expect($restoreResponse->json('data.task.archived_at'))->toBeNull();

    $this->postJson("/api/tasks/{$ctx['task']->task_id}/archive")
        ->assertStatus(404)
        ->assertJsonPath('message_code', 'resource.not_found');
    $this->postJson("/api/tasks/{$ctx['task']->task_id}/restore")
        ->assertStatus(404)
        ->assertJsonPath('message_code', 'resource.not_found');
    $this->postJson("/api/tasks/{$ctx['task']->task_id}/complete")
        ->assertStatus(404)
        ->assertJsonPath('message_code', 'resource.not_found');
    $this->postJson("/api/tasks/{$ctx['task']->task_id}/uncomplete")
        ->assertStatus(404)
        ->assertJsonPath('message_code', 'resource.not_found');
});

it('allows validator-only members to transition done status but not edit fields', function () {
    $ctx = createOwnedThemeAndTask();

    $validator = User::factory()->create([
        'email_verified_at' => now(),
    ]);

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

    Sanctum::actingAs($validator, [TokenService::ACCESS_ABILITY]);

    $this->patchJson("/api/tasks/{$ctx['task']->task_id}", [
        'status' => 'done',
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.task.status', 'done');

    $this->patchJson("/api/tasks/{$ctx['task']->task_id}", [
        'title' => 'validator-cannot-edit-title',
    ])
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');
});

it('sets default playground through PATCH and removes set-default action route', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $currentDefault = Playground::query()
        ->where('user_id', $user->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $secondary = Playground::factory()->create([
        'user_id' => $user->user_id,
        'is_default' => false,
    ]);

    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $this->patchJson("/api/playgrounds/{$secondary->playground_id}", [
        'is_default' => true,
    ])
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'playground.update.success');

    expect($secondary->fresh()->is_default)->toBeTrue();
    expect($currentDefault->fresh()->is_default)->toBeFalse();

    $this->postJson("/api/playgrounds/{$secondary->playground_id}/set-default")
        ->assertStatus(404)
        ->assertJsonPath('message_code', 'resource.not_found');
});

it('removes non-crud playground routes', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $playground = Playground::factory()->create([
        'user_id' => $user->user_id,
        'slug' => 'legacy-playground-route',
        'is_default' => false,
    ]);

    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $this->getJson("/api/playgrounds/{$playground->playground_id}/themes")
        ->assertStatus(404)
        ->assertJsonPath('message_code', 'resource.not_found');

    $this->getJson("/api/playgrounds/{$playground->playground_id}/stats")
        ->assertStatus(404)
        ->assertJsonPath('message_code', 'resource.not_found');

    $this->getJson('/api/playgrounds/by-slug/legacy-playground-route')
        ->assertStatus(404)
        ->assertJsonPath('message_code', 'resource.not_found');
});

it('updates member status and target playground through PATCH and removes action routes', function () {
    $ctx = createOwnedThemeAndTask();

    $member = User::factory()->create([
        'email_verified_at' => now(),
    ]);

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

    Sanctum::actingAs($ctx['owner'], [TokenService::ACCESS_ABILITY]);

    $this->patchJson("/api/themes/{$ctx['theme']->theme_id}/members/{$member->user_id}", [
        'status' => 'revoked',
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'revoked');

    $this->patchJson("/api/themes/{$ctx['theme']->theme_id}/members/{$member->user_id}", [
        'status' => 'active',
    ])->assertStatus(200);

    Sanctum::actingAs($member, [TokenService::ACCESS_ABILITY]);

    $newTargetPlayground = Playground::factory()->create([
        'user_id' => $member->user_id,
        'is_default' => false,
    ]);

    $this->patchJson("/api/themes/{$ctx['theme']->theme_id}/members/{$member->user_id}", [
        'target_playground_id' => $newTargetPlayground->playground_id,
    ])
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'theme.move.success');

    $this->postJson("/api/themes/{$ctx['theme']->theme_id}/members/{$member->user_id}/deactivate")
        ->assertStatus(404)
        ->assertJsonPath('message_code', 'resource.not_found');
    $this->postJson("/api/themes/{$ctx['theme']->theme_id}/members/{$member->user_id}/reactivate")
        ->assertStatus(404)
        ->assertJsonPath('message_code', 'resource.not_found');
    $this->patchJson("/api/themes/{$ctx['theme']->theme_id}/members/{$member->user_id}/move-to-playground", [
        'target_playground_id' => $newTargetPlayground->playground_id,
    ])
        ->assertStatus(404)
        ->assertJsonPath('message_code', 'resource.not_found');
    $this->postJson("/api/themes/{$ctx['theme']->theme_id}/leave")
        ->assertStatus(404)
        ->assertJsonPath('message_code', 'resource.not_found');

    $this->deleteJson("/api/themes/{$ctx['theme']->theme_id}/members/{$member->user_id}")
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'theme.member.left');

    expect(ThemeUserPermission::query()
        ->where('theme_id', $ctx['theme']->theme_id)
        ->where('user_id', $member->user_id)
        ->exists())->toBeFalse();
});

it('rejects incoherent member permission graph on PATCH /api/themes/{theme}/members/{userId}', function () {
    $ctx = createOwnedThemeAndTask();

    $member = User::factory()->create([
        'email_verified_at' => now(),
    ]);

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

    Sanctum::actingAs($ctx['owner'], [TokenService::ACCESS_ABILITY]);

    $this->patchJson("/api/themes/{$ctx['theme']->theme_id}/members/{$member->user_id}", [
        'can_view' => false,
        'can_edit_task' => true,
    ])
        ->assertStatus(422)
        ->assertJsonPath('message_code', 'theme.permissions.invalid');
});
