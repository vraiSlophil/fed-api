<?php

use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;
use App\Models\Themes\Theme;
use Laravel\Sanctum\Sanctum;

it('allows the theme owner to search for users', function () {
    $owner = User::factory()->create();
    $ownerPlayground = Playground::where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $searchUser = User::factory()->create([
        'username' => 'searchtarget',
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($owner, ['access']);

    $this->getJson('/api/users/search?search=sea&theme_id='.$theme->theme_id)
        ->assertStatus(200)
        ->assertJsonPath('message_code', 'theme.users.search.success')
        ->assertJsonFragment([
            'user_id' => $searchUser->user_id,
            'username' => $searchUser->username,
        ]);
});

it('rejects user search when the requester is not the theme owner', function () {
    $owner = User::factory()->create();
    $ownerPlayground = Playground::where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $otherUser = User::factory()->create();

    Sanctum::actingAs($otherUser, ['access']);

    $this->getJson('/api/users/search?search=sea&theme_id='.$theme->theme_id)
        ->assertStatus(403)
        ->assertJsonPath('message_code', 'permission.denied');
});

it('rejects user search without authentication', function () {
    $owner = User::factory()->create();
    $ownerPlayground = Playground::where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $this->getJson('/api/users/search?search=sea&theme_id='.$theme->theme_id)
        ->assertStatus(401)
        ->assertJsonPath('message_code', 'auth.failed');
});
