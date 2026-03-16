<?php

use App\Models\Auth\User;
use App\Models\Invitations\Invitation;
use App\Models\Playgrounds\Playground;
use App\Models\Themes\Theme;
use App\Models\Themes\ThemeUserPermission;

it('returns the authenticated caller via GET /api/users/me', function () {
    $user = User::factory()->create();

    actingAsAccessUser($user);

    $this->getJson('/api/users/me')
        ->assertOk()
        ->assertJsonPath('message_code', 'auth.user.fetched')
        ->assertJsonPath('data.user_id', $user->user_id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.role_power', $user->role_power);
});

it('rejects GET /api/users/me without authentication', function () {
    $this->getJson('/api/users/me')
        ->assertUnauthorized()
        ->assertJsonPath('message_code', 'auth.failed');
});

it('allows admin users to list accounts via GET /api/users', function () {
    $admin = User::factory()->create([
        'role_power' => 100,
    ]);

    $listedUser = User::factory()->create();

    actingAsAccessUser($admin);

    $this->getJson('/api/users')
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonFragment([
            'user_id' => $listedUser->user_id,
            'email' => $listedUser->email,
        ]);
});

it('supports search and theme_id query params via GET /api/users', function () {
    $admin = User::factory()->create([
        'role_power' => 100,
    ]);

    $owner = User::factory()->create();

    $ownerPlayground = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $searchUser = User::factory()->create([
        'username' => 'query-target-user',
    ]);

    actingAsAccessUser($admin);

    $this->getJson('/api/users?search=query-target&theme_id='.$theme->theme_id)
        ->assertOk()
        ->assertJsonFragment([
            'user_id' => $searchUser->user_id,
            'username' => $searchUser->username,
        ]);
});

it('forbids non-admin users from listing accounts via GET /api/users', function () {
    $user = User::factory()->create();

    actingAsAccessUser($user);

    $this->getJson('/api/users')
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');
});

it('allows theme owners to search invitable users via GET /api/users?theme_id=&search=', function () {
    $owner = User::factory()->create();

    $ownerPlayground = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $searchUser = User::factory()->create([
        'username' => 'member-search-target',
    ]);

    actingAsAccessUser($owner);

    $this->getJson('/api/users?theme_id='.$theme->theme_id.'&search=member-search')
        ->assertOk()
        ->assertJsonPath('message_code', 'theme.users.search.success')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user_id', $searchUser->user_id)
        ->assertJsonFragment([
            'user_id' => $searchUser->user_id,
            'username' => $searchUser->username,
        ]);
});

it('excludes existing members and pending invitees from theme user search mode', function () {
    $owner = User::factory()->create();

    $ownerPlayground = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $existingMember = User::factory()->create([
        'username' => 'invite-filter-member',
    ]);
    $existingMemberPlayground = Playground::query()
        ->where('user_id', $existingMember->user_id)
        ->where('is_default', true)
        ->firstOrFail();
    ThemeUserPermission::factory()->create([
        'theme_id' => $theme->theme_id,
        'user_id' => $existingMember->user_id,
        'target_playground_id' => $existingMemberPlayground->playground_id,
        'can_view' => true,
        'can_update_theme' => false,
        'can_add_task' => false,
        'can_edit_task' => false,
        'can_delete_task' => false,
        'can_validate_task' => false,
        'status' => 'active',
    ]);

    $pendingInvitee = User::factory()->create([
        'username' => 'invite-filter-pending',
    ]);
    Invitation::factory()->create([
        'inviter_user_id' => $owner->user_id,
        'invitee_user_id' => $pendingInvitee->user_id,
        'invitable_type' => Theme::class,
        'invitable_id' => $theme->theme_id,
        'status' => 'pending',
    ]);

    $eligibleUser = User::factory()->create([
        'username' => 'invite-filter-eligible',
    ]);

    actingAsAccessUser($owner);

    $response = $this->getJson('/api/users?theme_id='.$theme->theme_id.'&search=invite-filter');

    $response
        ->assertOk()
        ->assertJsonPath('message_code', 'theme.users.search.success');

    $userIds = collect($response->json('data'))->pluck('user_id')->all();

    expect($userIds)->toContain($eligibleUser->user_id);
    expect($userIds)->not->toContain($existingMember->user_id);
    expect($userIds)->not->toContain($pendingInvitee->user_id);
});

it('forbids non-owner users from using theme member search mode', function () {
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

    $this->getJson('/api/users?theme_id='.$theme->theme_id.'&search=member')
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');
});

it('rejects GET /api/users without authentication', function () {
    $this->getJson('/api/users')
        ->assertUnauthorized()
        ->assertJsonPath('message_code', 'auth.failed');
});

it('returns 404 on removed GET /api/users/search endpoint', function () {
    $admin = User::factory()->create([
        'role_power' => 100,
    ]);

    actingAsAccessUser($admin);

    $this->getJson('/api/users/search?search=john')
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');
});

it('allows admin users to read account details via GET /api/users/{user}', function () {
    $admin = User::factory()->create([
        'role_power' => 100,
    ]);

    $target = User::factory()->create();

    actingAsAccessUser($admin);

    $this->getJson("/api/users/{$target->user_id}")
        ->assertOk()
        ->assertJsonPath('message_code', 'user.show.success')
        ->assertJsonPath('data.user.user_id', $target->user_id);
});

it('forbids non-admin users from reading account details via GET /api/users/{user}', function () {
    $user = User::factory()->create();

    $target = User::factory()->create();

    actingAsAccessUser($user);

    $this->getJson("/api/users/{$target->user_id}")
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');
});

it('returns 404 on removed admin users route group', function () {
    $admin = User::factory()->create([
        'role_power' => 100,
    ]);

    actingAsAccessUser($admin);

    $this->getJson('/api/admin/users')
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');
});

it('returns 404 on removed profile route group', function () {
    $user = User::factory()->create();

    actingAsAccessUser($user);

    $this->getJson('/api/profile')
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');
});
