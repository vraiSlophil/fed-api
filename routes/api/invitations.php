<?php

use App\Http\Controllers\Invitations\ThemeInvitationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'access-token'])->group(function () {
    Route::patch('/invitations/{invitation:invitation_id}', [ThemeInvitationController::class, 'respond'])
        ->middleware(['signed:relative', 'throttle:6,1', 'can:respond,invitation'])
        ->name('invitations.respond');
});

Route::middleware(['auth:sanctum', 'access-token', 'verified'])->group(function () {
    Route::get('/invitations', [ThemeInvitationController::class, 'index'])->name('invitations.index');
});
