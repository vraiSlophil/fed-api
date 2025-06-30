<?php

use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\UserController;
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

    Route::get('/media/{path}', [MediaController::class, 'show'])
        ->where('path', '.*')
        ->name('media.show');

/*
|----------------------------------------------------------------------
| Routes protégées par jeton Sanctum uniquement
|----------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', LogoutController::class)
        ->name('logout');
});

/*
|----------------------------------------------------------------------
| Routes protégées par jeton Sanctum et vérification d’e-mail
|----------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/ping', function () {return ApiResponse::success(message: 'pong');})
        ->name('ping');

    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('profile.show');
        Route::post('/update', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::post('/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    });
    Route::prefix('themes')->group(function () {
        Route::get('/', [ThemeController::class, 'index'])->name('themes.index');
        Route::post('/', [ThemeController::class, 'store'])->name('themes.store');
        Route::get('/{id}', [ThemeController::class, 'show'])->name('themes.show');
        Route::put('/{id}', [ThemeController::class, 'update'])->name('themes.update');
        Route::delete('/{id}', [ThemeController::class, 'destroy'])->name('themes.destroy');
    });
    Route::post('/email/verification-notification', EmailVerificationNotificationController::class)
        ->middleware('throttle:6,1')
        ->name('verification.send');
    Route::get('/user', UserController::class)
        ->name('user');
});
