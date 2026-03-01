<?php

use App\Http\Controllers\Tasks\TaskController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'access-token', 'verified'])->group(function () {
    Route::prefix('tasks')->name('tasks.')->group(function () {
        Route::get('', [TaskController::class, 'index'])->name('index');
        Route::post('', [TaskController::class, 'store'])->name('store');

        Route::prefix('{task:task_id}')->whereUuid('task')->group(function () {
            Route::get('', [TaskController::class, 'show'])->middleware('can:view,task')->name('show');
            Route::patch('', [TaskController::class, 'update'])->middleware('can:update,task')->name('update');
            Route::delete('', [TaskController::class, 'destroy'])->middleware('can:delete,task')->name('destroy');
        });
    });
});
