<?php

use App\Http\Controllers\Invitations\ThemeInvitationController;
use Illuminate\Support\Facades\Route;

Route::prefix('invitations')->name('invitations.')->group(function () {
    Route::middleware(['auth:sanctum', 'access-token', 'verified'])->group(function () {
        Route::post('', [ThemeInvitationController::class, 'store'])->name('store');
        Route::get('', [ThemeInvitationController::class, 'index'])->name('index');

        Route::get('{invitation}', [ThemeInvitationController::class, 'show'])
            ->whereUuid('invitation')
            ->name('show');
        Route::delete('{invitation}', [ThemeInvitationController::class, 'destroy'])
            ->whereUuid('invitation')
            ->name('destroy');
        Route::patch('{invitation}', [ThemeInvitationController::class, 'respond'])
            ->whereUuid('invitation')
            ->middleware('throttle:6,1')
            ->name('respond');
    });
});
