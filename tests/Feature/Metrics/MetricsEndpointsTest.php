<?php

use App\Domain\Auth\Services\TokenService;
use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;
use App\Models\Themes\Theme;
use Laravel\Sanctum\Sanctum;

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
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $this->getJson('/api/stats')
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'stats.global.success')
        ->assertJsonStructure(['data']);
});

it('returns user metrics for a valid period and rejects invalid period', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $this->getJson('/api/user/stats?period=30_days')
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'user.metrics.retrieved');

    $this->getJson('/api/user/stats?period=invalid')
        ->assertStatus(422)
        ->assertJsonPath('message_code', 'validation.invalid');
});

it('returns theme stats for owner and forbids outsider', function () {
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $theme = createOwnedThemeForMetrics($owner);

    Sanctum::actingAs($owner, [TokenService::ACCESS_ABILITY]);

    $this->getJson("/api/themes/{$theme->theme_id}/stats")
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'stats.theme.success');

    $outsider = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    Sanctum::actingAs($outsider, [TokenService::ACCESS_ABILITY]);

    $this->getJson("/api/themes/{$theme->theme_id}/stats")
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');
});

it('requires authentication on metrics endpoints', function () {
    $this->getJson('/api/stats')
        ->assertStatus(401)
        ->assertJsonPath('message_code', 'auth.failed');

    $this->getJson('/api/user/stats')
        ->assertStatus(401)
        ->assertJsonPath('message_code', 'auth.failed');
});
