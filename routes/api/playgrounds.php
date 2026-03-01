<?php

use App\Http\Controllers\Playgrounds\PlaygroundController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'access-token', 'verified'])->group(function () {
    Route::prefix('playgrounds')->name('playgrounds.')->group(function () {
        Route::get('', [PlaygroundController::class, 'index'])->name('index');
        Route::post('', [PlaygroundController::class, 'store'])->name('store');

        Route::prefix('{playground:playground_id}')->whereUuid('playground')->group(function () {
            Route::get('', [PlaygroundController::class, 'show'])->middleware('can:view,playground')->name('show');
            Route::get('themes', [PlaygroundController::class, 'themes'])->middleware('can:view,playground')->name('themes.index');
            Route::patch('', [PlaygroundController::class, 'update'])->middleware('can:update,playground')->name('update');
            Route::delete('', [PlaygroundController::class, 'destroy'])->middleware('can:delete,playground')->name('destroy');
            Route::get('stats', [PlaygroundController::class, 'stats'])->middleware('can:stats,playground')->name('stats');
        });

        Route::prefix('by-slug/{playground:slug}')->name('by_slug.')->group(function () {
            Route::get('', [PlaygroundController::class, 'showBySlug'])->middleware('can:view,playground')->name('show');
            Route::get('themes', [PlaygroundController::class, 'themesBySlug'])->middleware('can:view,playground')->name('themes');
        });
    });
});
