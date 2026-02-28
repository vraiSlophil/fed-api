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
        });
    });
});
