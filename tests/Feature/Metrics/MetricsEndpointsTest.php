<?php

use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;
use App\Models\Tasks\Task;
use App\Models\Themes\Theme;
use App\Models\Themes\ThemeUserPermission;
use Illuminate\Support\Facades\Route;

function createOwnedThemeForMetrics(User $owner): Theme
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

it('returns global stats for an authenticated verified user', function () {
    $user = User::factory()->create();

    actingAsAccessUser($user);

    $this->getJson('/api/stats')
        ->assertOk()
        ->assertJsonPath('message_code', 'stats.global.success')
        ->assertJsonStructure(['data']);
});

it('returns user metrics for a valid period and rejects invalid period', function () {
    $user = User::factory()->create();

    actingAsAccessUser($user);

    $this->getJson('/api/user/stats?period=30_days')
        ->assertOk()
        ->assertJsonPath('message_code', 'user.metrics.retrieved');

    $this->getJson('/api/user/stats?period=invalid')
        ->assertUnprocessable()
        ->assertJsonPath('message_code', 'validation.invalid');
});

it('registers the user stats route and removes legacy metrics route name', function () {
    expect(Route::has('user.stats'))->toBeTrue();
    expect(Route::has('user.metrics'))->toBeFalse();
});

it('returns 404 on removed GET /api/user/metrics endpoint', function () {
    $user = User::factory()->create();

    actingAsAccessUser($user);

    $this->getJson('/api/user/metrics')
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');
});

it('returns theme stats for owner and forbids outsider', function () {
    $owner = User::factory()->create();
    $theme = createOwnedThemeForMetrics($owner);

    actingAsAccessUser($owner);

    $this->getJson("/api/themes/{$theme->theme_id}/stats")
        ->assertOk()
        ->assertJsonPath('message_code', 'stats.theme.success');

    $outsider = User::factory()->create();
    actingAsAccessUser($outsider);

    $this->getJson("/api/themes/{$theme->theme_id}/stats")
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');
});

it('requires authentication on metrics endpoints', function () {
    $this->getJson('/api/stats')
        ->assertUnauthorized()
        ->assertJsonPath('message_code', 'auth.failed');

    $this->getJson('/api/user/stats')
        ->assertUnauthorized()
        ->assertJsonPath('message_code', 'auth.failed');
});

it('includes member-authored theme tasks in owner global stats visibility', function () {
    $owner = User::factory()->create();
    $theme = createOwnedThemeForMetrics($owner);

    $member = User::factory()->create();
    $memberDefaultPlayground = Playground::query()
        ->where('user_id', $member->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    ThemeUserPermission::factory()->create([
        'theme_id' => $theme->theme_id,
        'user_id' => $member->user_id,
        'target_playground_id' => $memberDefaultPlayground->playground_id,
        'can_view' => true,
        'can_update_theme' => false,
        'can_add_task' => true,
        'can_edit_task' => false,
        'can_delete_task' => false,
        'can_validate_task' => false,
        'status' => 'active',
    ]);

    Task::factory()->create([
        'theme_id' => $theme->theme_id,
        'user_id' => $member->user_id,
        'status' => 'todo',
        'archived_at' => null,
    ]);

    actingAsAccessUser($owner);

    $this->getJson('/api/stats')
        ->assertOk()
        ->assertJsonPath('data.total', 1);
});

it('does not expose revoked member tasks in revoked member global stats', function () {
    $owner = User::factory()->create();
    $theme = createOwnedThemeForMetrics($owner);

    $member = User::factory()->create();
    $memberDefaultPlayground = Playground::query()
        ->where('user_id', $member->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    ThemeUserPermission::factory()->create([
        'theme_id' => $theme->theme_id,
        'user_id' => $member->user_id,
        'target_playground_id' => $memberDefaultPlayground->playground_id,
        'can_view' => true,
        'can_update_theme' => true,
        'can_add_task' => true,
        'can_edit_task' => true,
        'can_delete_task' => true,
        'can_validate_task' => true,
        'status' => 'revoked',
    ]);

    Task::factory()->create([
        'theme_id' => $theme->theme_id,
        'user_id' => $member->user_id,
        'status' => 'todo',
        'archived_at' => null,
    ]);

    actingAsAccessUser($member);

    $this->getJson('/api/stats')
        ->assertOk()
        ->assertJsonPath('data.total', 0);
});
