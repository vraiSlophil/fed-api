<?php

use App\Http\Controllers\Tasks\TaskController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'access-token', 'verified'])->group(function () {
    Route::prefix('tasks')->group(function () {
        Route::get('/', [TaskController::class, 'index'])->name('tasks.index');
        Route::post('/', [TaskController::class, 'store'])->name('tasks.store');

        Route::prefix('/{task:task_id}')->group(function () {
            Route::get('', [TaskController::class, 'show'])->middleware('can:view,task')->name('tasks.show');
            Route::patch('', [TaskController::class, 'update'])->middleware('can:update,task')->name('tasks.update');
            Route::delete('', [TaskController::class, 'destroy'])->middleware('can:delete,task')->name('tasks.destroy');
            Route::post('/archive', [TaskController::class, 'archive'])->middleware('can:archive,task')->name('tasks.archive');
            Route::post('/restore', [TaskController::class, 'restore'])->middleware('can:restore,task')->name('tasks.restore');
            Route::post('/complete', [TaskController::class, 'complete'])->middleware('can:validate,task')->name('tasks.complete');
            Route::post('/uncomplete', [TaskController::class, 'uncomplete'])->middleware('can:validate,task')->name('tasks.uncomplete');
        });
    });
});
