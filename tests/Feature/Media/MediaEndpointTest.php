<?php

use Illuminate\Support\Facades\Storage;

it('streams a media file when it exists', function () {
    Storage::fake('public');
    Storage::disk('public')->put('avatars/demo.txt', 'hello-media');

    $response = $this->get('/api/media/avatars/demo.txt');

    $response->assertOk();
    expect($response->headers->get('content-type'))->not->toBeNull();
    expect((string) $response->headers->get('content-disposition'))->toContain('demo.txt');
});

it('returns 404 for missing media files', function () {
    Storage::fake('public');

    $this->getJson('/api/media/missing/file.txt')
        ->assertStatus(404)
        ->assertJsonPath('message_code', 'resource.not_found');
});

it('returns 404 for path traversal attempts', function () {
    $this->getJson('/api/media/../.env')
        ->assertStatus(404)
        ->assertJsonPath('message_code', 'resource.not_found');
});
