<?php

use App\Http\Controllers\Playgrounds\PlaygroundController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'access-token', 'verified'])->group(function () {
    Route::prefix('playgrounds')->name('playgrounds.')->group(function () {
        Route::get('', [PlaygroundController::class, 'index'])->name('index');
        Route::post('', [PlaygroundController::class, 'store'])->name('store');

        Route::prefix('{playground:playground_id}')->whereUuid('playground')->group(function () {
            Route::get('', [PlaygroundController::class, 'show'])->middleware('can:view,playground')->name('show');
            Route::patch('', [PlaygroundController::class, 'update'])->middleware('can:update,playground')->name('update');
            Route::delete('', [PlaygroundController::class, 'destroy'])->middleware('can:delete,playground')->name('destroy');
        });
    });
});
