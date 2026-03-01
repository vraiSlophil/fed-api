<?php

it('returns 405 with API envelope for method not allowed on api routes', function () {
    $this->postJson('/api/media/some-file.jpg')
        ->assertStatus(405)
        ->assertJsonPath('message_code', 'method.not_allowed');
});
