<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'access-token', 'verified'])->group(function () {
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('', [AdminUserController::class, 'index'])->name('index');
        Route::post('', [AdminUserController::class, 'store'])->middleware('admin')->name('store');
        Route::get('me', UserController::class)->name('me');

        Route::prefix('{user:user_id}')->whereUuid('user')->group(function () {
            Route::get('', [AdminUserController::class, 'show'])
                ->middleware(['admin', 'can:view,user'])
                ->name('show');

            Route::patch('', [AdminUserController::class, 'update'])
                ->middleware('can:update,user')
                ->name('update');

            Route::delete('', [AdminUserController::class, 'destroy'])
                ->middleware(['admin', 'can:delete,user'])
                ->name('destroy');
        });
    });
});
