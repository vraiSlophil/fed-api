<?php

use App\Http\Controllers\Playgrounds\PlaygroundController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'access-token', 'verified'])->group(function () {
    Route::prefix('playgrounds')->group(function () {
        Route::get('/', [PlaygroundController::class, 'index']);
        Route::post('/', [PlaygroundController::class, 'store']);

        Route::prefix('/{playground:playground_id}')->group(function () {
            Route::get('', [PlaygroundController::class, 'show'])->middleware('can:view,playground');
            Route::get('/themes', [PlaygroundController::class, 'themes'])->middleware('can:view,playground');
            Route::patch('', [PlaygroundController::class, 'update'])->middleware('can:update,playground');
            Route::delete('', [PlaygroundController::class, 'destroy'])->middleware('can:delete,playground');
            Route::post('/set-default', [PlaygroundController::class, 'setAsDefault'])->middleware('can:setDefault,playground');
            Route::get('/stats', [PlaygroundController::class, 'stats'])->middleware('can:stats,playground');
        });

        Route::prefix('/by-slug/{playground:slug}')->group(function () {
            Route::get('', [PlaygroundController::class, 'showBySlug'])->middleware('can:view,playground');
            Route::get('/themes', [PlaygroundController::class, 'themesBySlug'])->middleware('can:view,playground');
        });
    });
});
