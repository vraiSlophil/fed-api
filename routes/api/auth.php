<?php

use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RefreshTokenController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('register', RegisterController::class)->name('register');
    Route::post('login', LoginController::class)
        ->middleware('throttle:auth-login')
        ->name('login');
    Route::post('refresh', RefreshTokenController::class)
        ->middleware('throttle:auth-refresh')
        ->name('refresh');
    Route::post('forgot-password', PasswordResetLinkController::class)->name('password.email');
    Route::post('reset-password', NewPasswordController::class)->name('password.store');

    Route::middleware(['auth:sanctum', 'access-token'])->group(function () {
        Route::post('logout', LogoutController::class)->name('logout');
        Route::get('ping', function () {
            return ApiResponse::builder()
                ->success(message: 'pong')
                ->json();
        })->name('ping');
    });
});

Route::post('email-verifications', VerifyEmailController::class)
    ->middleware(['signed:relative', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware(['auth:sanctum', 'access-token'])->group(function () {
    Route::post('email-verification-notifications', EmailVerificationNotificationController::class)
        ->middleware('throttle:6,1')
        ->name('verification.send');
});
