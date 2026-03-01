<?php

use Illuminate\Support\Facades\Route;

it('registers strict auth route names', function () {
    expect(Route::has('auth.logout'))->toBeTrue();
    expect(Route::has('auth.ping'))->toBeTrue();
});

it('registers explicit playground route names without generated names', function () {
    $expected = [
        'playgrounds.index',
        'playgrounds.store',
        'playgrounds.show',
        'playgrounds.update',
        'playgrounds.destroy',
        'playgrounds.stats',
        'playgrounds.themes.index',
        'playgrounds.by_slug.show',
        'playgrounds.by_slug.themes',
    ];

    foreach ($expected as $routeName) {
        expect(Route::has($routeName))->toBeTrue();
    }

    $playgroundRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/playgrounds'));

    $generatedRouteNames = $playgroundRoutes
        ->map(fn ($route) => $route->getName())
        ->filter(fn ($name) => is_string($name) && str_starts_with($name, 'generated::'));

    expect($generatedRouteNames->isEmpty())->toBeTrue();
});
