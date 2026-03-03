<?php

use App\Domain\Auth\Services\TokenService;
use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;
use App\Models\Themes\Theme;
use App\Models\Themes\ThemeUserPermission;
use Laravel\Sanctum\Sanctum;

it('creates and lists themes for owners and invited members', function () {
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $ownerPlayground = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    Sanctum::actingAs($owner, [TokenService::ACCESS_ABILITY]);

    $createResponse = $this->postJson('/api/themes', [
        'title' => 'Owner Theme',
        'color' => '#00AACC',
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $createResponse->assertStatus(201)
        ->assertJsonPath('message_code', 'theme.create.success');

    $themeId = (string) $createResponse->json('data.theme.theme_id');

    $this->getJson('/api/themes')
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'theme.list.success')
        ->assertJsonFragment(['theme_id' => $themeId]);

    $member = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $memberPlayground = Playground::query()
        ->where('user_id', $member->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    ThemeUserPermission::factory()->create([
        'theme_id' => $themeId,
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

    $this->getJson('/api/themes')
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'theme.list.success')
        ->assertJsonFragment(['theme_id' => $themeId]);

    $this->getJson('/api/themes?playground_id='.$memberPlayground->playground_id)
        ->assertStatus(200)
        ->assertJsonFragment(['theme_id' => $themeId]);
});

it('rejects theme creation on non-owned playground', function () {
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $other = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $otherDefault = Playground::query()
        ->where('user_id', $other->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    Sanctum::actingAs($owner, [TokenService::ACCESS_ABILITY]);

    $this->postJson('/api/themes', [
        'title' => 'Invalid Theme',
        'color' => '#AA00CC',
        'playground_id' => $otherDefault->playground_id,
    ])
        ->assertStatus(404)
        ->assertJsonPath('message_code', 'resource.not_found');
});

it('validates hex color format on theme create', function () {
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $ownerPlayground = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    Sanctum::actingAs($owner, [TokenService::ACCESS_ABILITY]);

    $this->postJson('/api/themes', [
        'title' => 'Invalid color',
        'color' => 'blue',
        'playground_id' => $ownerPlayground->playground_id,
    ])
        ->assertStatus(422)
        ->assertJsonPath('message_code', 'validation.invalid');
});

it('returns theme stats for owner and lists members for manageMembers owner', function () {
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $ownerPlayground = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

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

    Sanctum::actingAs($owner, [TokenService::ACCESS_ABILITY]);

    $this->getJson("/api/themes/{$theme->theme_id}/stats")
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'stats.theme.success');

    $this->getJson("/api/themes/{$theme->theme_id}/members")
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'theme.members.list.success')
        ->assertJsonStructure(['data' => ['members']]);
});

it('forbids non-owners from listing theme members', function () {
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $ownerPlayground = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $outsider = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    Sanctum::actingAs($outsider, [TokenService::ACCESS_ABILITY]);

    $this->getJson("/api/themes/{$theme->theme_id}/members")
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');
});

it('shows updates and deletes a theme for its owner', function () {
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $ownerPlayground = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
        'title' => 'Before title',
        'color' => '#111111',
    ]);

    Sanctum::actingAs($owner, [TokenService::ACCESS_ABILITY]);

    $this->getJson("/api/themes/{$theme->theme_id}")
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'theme.show.success')
        ->assertJsonPath('data.theme.theme_id', $theme->theme_id);

    $this->patchJson("/api/themes/{$theme->theme_id}", [
        'title' => 'After title',
        'color' => '#222222',
    ])
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'theme.update.success')
        ->assertJsonPath('data.theme.title', 'After title')
        ->assertJsonPath('data.theme.color', '#222222');

    $this->deleteJson("/api/themes/{$theme->theme_id}")
        ->assertNoContent();

    expect(Theme::query()->where('theme_id', $theme->theme_id)->exists())->toBeFalse();
});

it('validates hex color format on theme update', function () {
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $ownerPlayground = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
        'title' => 'Before title',
        'color' => '#111111',
    ]);

    Sanctum::actingAs($owner, [TokenService::ACCESS_ABILITY]);

    $this->patchJson("/api/themes/{$theme->theme_id}", [
        'color' => '123456',
    ])
        ->assertStatus(422)
        ->assertJsonPath('message_code', 'validation.invalid');
});

it('forbids outsider access to theme show update and delete', function () {
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $ownerPlayground = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $outsider = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    Sanctum::actingAs($outsider, [TokenService::ACCESS_ABILITY]);

    $this->getJson("/api/themes/{$theme->theme_id}")
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');

    $this->patchJson("/api/themes/{$theme->theme_id}", [
        'title' => 'Nope',
    ])
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');

    $this->deleteJson("/api/themes/{$theme->theme_id}")
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');
});

it('allows member with can_update_theme to patch but not delete the theme', function () {
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $ownerPlayground = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();
    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

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
        'can_update_theme' => true,
        'can_add_task' => false,
        'can_edit_task' => false,
        'can_delete_task' => false,
        'can_validate_task' => false,
        'status' => 'active',
    ]);

    Sanctum::actingAs($member, [TokenService::ACCESS_ABILITY]);

    $this->patchJson("/api/themes/{$theme->theme_id}", [
        'title' => 'Member update title',
    ])
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'theme.update.success')
        ->assertJsonPath('data.theme.title', 'Member update title');

    $this->deleteJson("/api/themes/{$theme->theme_id}")
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');
});

it('requires authentication for theme crud routes', function () {
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $ownerPlayground = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();
    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $this->getJson('/api/themes')
        ->assertStatus(401)
        ->assertJsonPath('message_code', 'auth.failed');

    $this->postJson('/api/themes', [
        'title' => 'No auth',
        'color' => '#123456',
        'playground_id' => $ownerPlayground->playground_id,
    ])
        ->assertStatus(401)
        ->assertJsonPath('message_code', 'auth.failed');

    $this->getJson("/api/themes/{$theme->theme_id}")
        ->assertStatus(401)
        ->assertJsonPath('message_code', 'auth.failed');

    $this->patchJson("/api/themes/{$theme->theme_id}", [
        'title' => 'No auth patch',
    ])
        ->assertStatus(401)
        ->assertJsonPath('message_code', 'auth.failed');

    $this->deleteJson("/api/themes/{$theme->theme_id}")
        ->assertStatus(401)
        ->assertJsonPath('message_code', 'auth.failed');
});
