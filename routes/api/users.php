<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'access-token', 'verified'])->group(function () {
    Route::get('/users', [AdminUserController::class, 'index'])->middleware('admin')->name('users.index');
    Route::post('/users', [AdminUserController::class, 'store'])->middleware('admin')->name('users.store');
    Route::get('/users/me', UserController::class)->name('users.me');

    Route::get('/users/{user:user_id}', [AdminUserController::class, 'show'])
        ->whereUuid('user')
        ->middleware(['admin', 'can:view,user'])
        ->name('users.show');

    Route::patch('/users/{user:user_id}', [AdminUserController::class, 'update'])
        ->whereUuid('user')
        ->middleware('can:update,user')
        ->name('users.update');

    Route::delete('/users/{user:user_id}', [AdminUserController::class, 'destroy'])
        ->whereUuid('user')
        ->middleware(['admin', 'can:delete,user'])
        ->name('users.destroy');
});
