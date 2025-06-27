<?php

use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Route;

/*
|-----------------------------------------------------------------------
| API Routes
|-----------------------------------------------------------------------
*/

/*
|----------------------------------------------------------------------
| Routes publiques (utilisateur non authentifié)
|----------------------------------------------------------------------
*/

    Route::post('/register', RegisterController::class)
        ->name('register');
    Route::post('/login', LoginController::class)
        ->name('login');
    Route::post('/forgot-password', PasswordResetLinkController::class)
        ->name('password.email');
    Route::post('/reset-password', NewPasswordController::class)
        ->name('password.store');

    // Vérification d’e-mail via URL signée
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

/*
|----------------------------------------------------------------------
| Routes protégées par jeton Sanctum uniquement
|----------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', LogoutController::class)
        ->name('logout');

    Route::get('/ping', function () {return ApiResponse::success(message: 'pong');})
        ->name('ping');
});

/*
|----------------------------------------------------------------------
| Routes protégées par jeton Sanctum et vérification d’e-mail
|----------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', EnsureEmailIsVerified::class])->group(function () {

    // Envoi d’un nouveau mail de vérification
    Route::post('/email/verification-notification', EmailVerificationNotificationController::class)
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Exemple de route protégée retournant l’utilisateur
    Route::get('/user', UserController::class)
        ->name('user');
});
