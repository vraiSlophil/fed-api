<?php

use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;

it('lists only playgrounds owned by the authenticated user', function () {
    $owner = User::factory()->create();

    $ownerDefault = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $extraOwned = Playground::factory()->create([
        'user_id' => $owner->user_id,
        'slug' => 'owner-extra',
        'is_default' => false,
    ]);

    $otherUser = User::factory()->create();
    Playground::factory()->create([
        'user_id' => $otherUser->user_id,
        'slug' => 'other-private',
        'is_default' => false,
    ]);

    actingAsAccessUser($owner);

    $this->getJson('/api/playgrounds')
        ->assertOk()
        ->assertJsonPath('message_code', 'playground.list.success')
        ->assertJsonFragment(['playground_id' => $ownerDefault->playground_id])
        ->assertJsonFragment(['playground_id' => $extraOwned->playground_id])
        ->assertJsonMissing(['slug' => 'other-private']);
});

it('creates a playground for the authenticated user', function () {
    $user = User::factory()->create();
    actingAsAccessUser($user);

    $response = $this->postJson('/api/playgrounds', [
        'name' => 'Client Workspace',
        'slug' => 'client-workspace',
        'color' => '#112233',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message_code', 'playground.create.success')
        ->assertJsonPath('data.playground.user_id', $user->user_id)
        ->assertJsonPath('data.playground.name', 'Client Workspace')
        ->assertJsonPath('data.playground.slug', 'client-workspace');
});

it('validates duplicate playground slug for the same owner on create', function () {
    $user = User::factory()->create();
    Playground::factory()->create([
        'user_id' => $user->user_id,
        'slug' => 'duplicate-slug',
        'is_default' => false,
    ]);

    actingAsAccessUser($user);

    $this->postJson('/api/playgrounds', [
        'name' => 'Another',
        'slug' => 'duplicate-slug',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message_code', 'validation.invalid');
});

it('validates missing slug on create', function () {
    $user = User::factory()->create();

    actingAsAccessUser($user);

    $this->postJson('/api/playgrounds', [
        'name' => 'Client Workspace',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message_code', 'validation.invalid');
});

it('shows a playground by id for its owner', function () {
    $user = User::factory()->create();
    $playground = Playground::factory()->create([
        'user_id' => $user->user_id,
        'slug' => 'show-by-id',
        'is_default' => false,
    ]);

    actingAsAccessUser($user);

    $this->getJson("/api/playgrounds/{$playground->playground_id}")
        ->assertOk()
        ->assertJsonPath('message_code', 'playground.show.success')
        ->assertJsonPath('data.playground.playground_id', $playground->playground_id)
        ->assertJsonPath('data.playground.name', $playground->name);
});

it('returns a single playground when filtering index by slug', function () {
    $user = User::factory()->create();

    $playground = Playground::factory()->create([
        'user_id' => $user->user_id,
        'slug' => 'slug-target',
        'is_default' => false,
    ]);

    actingAsAccessUser($user);

    $this->getJson('/api/playgrounds?slug=slug-target')
        ->assertOk()
        ->assertJsonPath('message_code', 'playground.show.success')
        ->assertJsonPath('data.playground.playground_id', $playground->playground_id)
        ->assertJsonPath('data.playground.slug', 'slug-target');
});

it('forbids reading another user playground by id', function () {
    $owner = User::factory()->create();
    $playground = Playground::factory()->create([
        'user_id' => $owner->user_id,
        'slug' => 'owner-id-only',
        'is_default' => false,
    ]);

    $outsider = User::factory()->create();
    actingAsAccessUser($outsider);

    $this->getJson("/api/playgrounds/{$playground->playground_id}")
        ->assertForbidden()
        ->assertJsonPath('message_code', 'permission.denied');
});

it('returns 404 when playground slug does not belong to the authenticated user', function () {
    $owner = User::factory()->create();

    Playground::factory()->create([
        'user_id' => $owner->user_id,
        'slug' => 'private-owner-slug',
        'is_default' => false,
    ]);

    $outsider = User::factory()->create();

    actingAsAccessUser($outsider);

    $this->getJson('/api/playgrounds?slug=private-owner-slug')
        ->assertNotFound()
        ->assertJsonPath('message_code', 'resource.not_found');
});

it('updates a playground and validates duplicate slug on update', function () {
    $user = User::factory()->create();
    $first = Playground::factory()->create([
        'user_id' => $user->user_id,
        'slug' => 'first-slug',
        'is_default' => false,
    ]);
    $second = Playground::factory()->create([
        'user_id' => $user->user_id,
        'slug' => 'second-slug',
        'is_default' => false,
    ]);

    actingAsAccessUser($user);

    $this->patchJson("/api/playgrounds/{$first->playground_id}", [
        'name' => 'Renamed',
        'slug' => 'renamed-slug',
    ])
        ->assertOk()
        ->assertJsonPath('message_code', 'playground.update.success')
        ->assertJsonPath('data.playground.name', 'Renamed')
        ->assertJsonPath('data.playground.slug', 'renamed-slug');

    $this->patchJson("/api/playgrounds/{$first->playground_id}", [
        'slug' => 'second-slug',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message_code', 'validation.invalid');

    $this->patchJson("/api/playgrounds/{$first->playground_id}", [
        'slug' => null,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message_code', 'validation.invalid');

    expect($second->fresh()->slug)->toBe('second-slug');
});

it('deletes a non-default playground with 204 and forbids deleting default playground', function () {
    $user = User::factory()->create();
    $defaultPlayground = Playground::query()
        ->where('user_id', $user->user_id)
        ->where('is_default', true)
        ->firstOrFail();
    $secondary = Playground::factory()->create([
        'user_id' => $user->user_id,
        'slug' => 'to-delete',
        'is_default' => false,
    ]);

    actingAsAccessUser($user);

    $this->deleteJson("/api/playgrounds/{$secondary->playground_id}")
        ->assertNoContent();

    expect(Playground::query()->where('playground_id', $secondary->playground_id)->exists())->toBeFalse();

    $this->deleteJson("/api/playgrounds/{$defaultPlayground->playground_id}")
        ->assertStatus(400)
        ->assertJsonPath('message_code', 'playground.delete.default_forbidden');
});

it('requires authentication for playground CRUD routes', function () {
    $user = User::factory()->create();
    $playground = Playground::factory()->create([
        'user_id' => $user->user_id,
        'is_default' => false,
    ]);

    $this->getJson('/api/playgrounds')
        ->assertUnauthorized()
        ->assertJsonPath('message_code', 'auth.failed');

    $this->postJson('/api/playgrounds', [
        'name' => 'No Auth',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('message_code', 'auth.failed');

    $this->patchJson("/api/playgrounds/{$playground->playground_id}", [
        'name' => 'No Auth',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('message_code', 'auth.failed');

    $this->deleteJson("/api/playgrounds/{$playground->playground_id}")
        ->assertUnauthorized()
        ->assertJsonPath('message_code', 'auth.failed');
});
