<?php

use App\Models\Playground;
use App\Models\Theme;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('autorise le proprietaire du theme a rechercher des utilisateurs', function () {
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

it('refuse la recherche utilisateur si le demandeur n est pas proprietaire du theme', function () {
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
