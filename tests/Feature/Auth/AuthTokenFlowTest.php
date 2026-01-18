<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

it('login renvoie un token et permet d\'appeler une route protégée', function () {
    $user = User::factory()->create([
        'password' => bcrypt('secret-password'),
        'email_verified_at' => now(),
    ]);

    $login = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'secret-password',
    ]);

    $login->assertStatus(200);
    expect($login->json('data.token'))->toBeString();

    $token = $login->json('data.token');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/ping')
        ->assertStatus(200);
});

it('une route protégée renvoie 401 sans token', function () {
    $this->getJson('/api/ping')->assertStatus(401);
});

it('logout révoque le token courant (suppression en base)', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $token = $user->createToken('test-token')->plainTextToken;

    // sanity check: le token existe
    expect(PersonalAccessToken::where('tokenable_id', $user->getAuthIdentifier())->count())
        ->toBeGreaterThan(0);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/logout')
        ->assertStatus(200);

    // Le token doit être supprimé en base (source de vérité)
    expect(PersonalAccessToken::where('tokenable_id', $user->getAuthIdentifier())->count())
        ->toBe(0);
});
