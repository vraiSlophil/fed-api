<?php

it('derives allowed cors origins from the configured frontend url', function () {
    config()->set('app.frontend_url', 'https://front.example.test/');
    config()->set('app.url', 'http://api.example.test');

    $config = require config_path('cors.php');

    expect($config['allowed_origins'])->toBe(['https://front.example.test']);
});

it('allows cors preflight requests from the configured frontend origin', function () {
    config()->set('cors.allowed_origins', ['https://front.example.test']);

    $response = $this->call('OPTIONS', '/api/auth/login', server: [
        'HTTP_ORIGIN' => 'https://front.example.test',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Content-Type, Authorization',
    ]);

    $response
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', 'https://front.example.test');
});
