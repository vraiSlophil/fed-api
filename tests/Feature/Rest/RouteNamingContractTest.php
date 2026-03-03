<?php

use Illuminate\Support\Facades\Route;

it('registers strict auth route names', function () {
    expect(Route::has('auth.logout'))->toBeTrue();
    expect(Route::has('auth.ping'))->toBeTrue();
});

it('registers user stats route name and removes legacy user metrics route name', function () {
    expect(Route::has('user.stats'))->toBeTrue();
    expect(Route::has('user.metrics'))->toBeFalse();
});

it('registers explicit playground route names without generated names', function () {
    $expected = [
        'playgrounds.index',
        'playgrounds.store',
        'playgrounds.show',
        'playgrounds.update',
        'playgrounds.destroy',
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

it('registers explicit theme and task crud route names', function () {
    $expected = [
        'themes.index',
        'themes.store',
        'themes.show',
        'themes.update',
        'themes.destroy',
        'tasks.index',
        'tasks.store',
        'tasks.show',
        'tasks.update',
        'tasks.destroy',
    ];

    foreach ($expected as $routeName) {
        expect(Route::has($routeName))->toBeTrue();
    }
});
