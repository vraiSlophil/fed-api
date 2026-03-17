<?php

use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;
use App\Models\Themes\Theme;
use App\Models\Themes\ThemeUserPermission;

it('creates and lists themes for owners and invited members', function () {
    $owner = User::factory()->create();
    $ownerPlayground = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    actingAsAccessUser($owner);

    $createResponse = $this->postJson('/api/themes', [
        'title' => 'Owner Theme',
        'color' => '#00AACC',
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $createResponse->assertCreated()
        ->assertJsonPath('message_code', 'theme.create.success');

    $themeId = (string) $createResponse->json('data.theme_id');

    $this->getJson('/api/themes')
        ->assertOk()
        ->assertJsonPath('message_code', 'theme.list.success')
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['theme_id' => $themeId]);

    $member = User::factory()->create();
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

    actingAsAccessUser($member);

    $this->getJson('/api/themes')
        ->assertOk()
        ->assertJsonPath('message_code', 'theme.list.success')
        ->assertJsonFragment(['theme_id' => $themeId]);

    $this->getJson('/api/themes?playground_id='.$memberPlayground->playground_id)
        ->assertOk()
        ->assertJsonFragment(['theme_id' => $themeId]);
});

it('rejects theme creation on non-owned playground', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $otherDefault = Playground::query()
        ->where('user_id', $other->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    actingAsAccessUser($owner);

    $this->postJson('/api/themes', [
        'title' => 'Invalid Theme',
        'color' => '#AA00CC',
        'playground_id' => $otherDefault->playground_id,
    ])
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');
});

it('validates hex color format on theme create', function () {
    $owner = User::factory()->create();
    $ownerPlayground = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    actingAsAccessUser($owner);

    $this->postJson('/api/themes', [
        'title' => 'Invalid color',
        'color' => 'blue',
        'playground_id' => $ownerPlayground->playground_id,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message_code', 'validation.invalid');
});

it('returns theme stats for owner and lists members for manageMembers owner', function () {
    $owner = User::factory()->create();
    $ownerPlayground = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

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

    actingAsAccessUser($owner);

    $this->getJson("/api/themes/{$theme->theme_id}/stats")
        ->assertOk()
        ->assertJsonPath('message_code', 'stats.theme.success');

    $this->getJson("/api/themes/{$theme->theme_id}/members")
        ->assertOk()
        ->assertJsonPath('message_code', 'theme.members.list.success')
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['user_id' => $owner->user_id])
        ->assertJsonFragment(['user_id' => $member->user_id]);
});

it('forbids non-owners from listing theme members', function () {
    $owner = User::factory()->create();
    $ownerPlayground = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $outsider = User::factory()->create();
    actingAsAccessUser($outsider);

    $this->getJson("/api/themes/{$theme->theme_id}/members")
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');
});

it('shows updates and deletes a theme for its owner', function () {
    $owner = User::factory()->create();
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

    actingAsAccessUser($owner);

    $this->getJson("/api/themes/{$theme->theme_id}")
        ->assertOk()
        ->assertJsonPath('message_code', 'theme.show.success')
        ->assertJsonPath('data.theme_id', $theme->theme_id);

    $this->patchJson("/api/themes/{$theme->theme_id}", [
        'title' => 'After title',
        'color' => '#222222',
    ])
        ->assertOk()
        ->assertJsonPath('message_code', 'theme.update.success')
        ->assertJsonPath('data.title', 'After title')
        ->assertJsonPath('data.color', '#222222');

    $this->deleteJson("/api/themes/{$theme->theme_id}")
        ->assertNoContent();

    expect(Theme::query()->where('theme_id', $theme->theme_id)->exists())->toBeFalse();
});

it('validates hex color format on theme update', function () {
    $owner = User::factory()->create();
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

    actingAsAccessUser($owner);

    $this->patchJson("/api/themes/{$theme->theme_id}", [
        'color' => '123456',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message_code', 'validation.invalid');
});

it('forbids outsider access to theme show update and delete', function () {
    $owner = User::factory()->create();
    $ownerPlayground = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $outsider = User::factory()->create();
    actingAsAccessUser($outsider);

    $this->getJson("/api/themes/{$theme->theme_id}")
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');

    $this->patchJson("/api/themes/{$theme->theme_id}", [
        'title' => 'Nope',
    ])
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');

    $this->deleteJson("/api/themes/{$theme->theme_id}")
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');
});

it('allows member with can_update_theme to patch but not delete the theme', function () {
    $owner = User::factory()->create();
    $ownerPlayground = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();
    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

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
        'can_update_theme' => true,
        'can_add_task' => false,
        'can_edit_task' => false,
        'can_delete_task' => false,
        'can_validate_task' => false,
        'status' => 'active',
    ]);

    actingAsAccessUser($member);

    $this->patchJson("/api/themes/{$theme->theme_id}", [
        'title' => 'Member update title',
    ])
        ->assertOk()
        ->assertJsonPath('message_code', 'theme.update.success')
        ->assertJsonPath('data.title', 'Member update title');

    $this->deleteJson("/api/themes/{$theme->theme_id}")
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');
});

it('requires authentication for theme crud routes', function () {
    $owner = User::factory()->create();
    $ownerPlayground = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();
    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $this->getJson('/api/themes')
        ->assertUnauthorized()
        ->assertJsonPath('message_code', 'auth.failed');

    $this->postJson('/api/themes', [
        'title' => 'No auth',
        'color' => '#123456',
        'playground_id' => $ownerPlayground->playground_id,
    ])
        ->assertUnauthorized()
        ->assertJsonPath('message_code', 'auth.failed');

    $this->getJson("/api/themes/{$theme->theme_id}")
        ->assertUnauthorized()
        ->assertJsonPath('message_code', 'auth.failed');

    $this->patchJson("/api/themes/{$theme->theme_id}", [
        'title' => 'No auth patch',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('message_code', 'auth.failed');

    $this->deleteJson("/api/themes/{$theme->theme_id}")
        ->assertUnauthorized()
        ->assertJsonPath('message_code', 'auth.failed');
});
