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
use App\Http\Controllers\StatsController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\ThemeInvitationController;
use App\Http\Controllers\ThemeMemberController;
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

    // Gestion des invitations aux thèmes via URL signée
    Route::get('/themes/invitation', [ThemeInvitationController::class, 'handleInvitation'])
        ->name('theme.accept-invitation');


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

    Route::get('/stats', [StatsController::class, 'globalStats'])->name('stats.global');
    Route::get('/users/search', [ThemeMemberController::class, 'searchUsers'])->name('users.search');

    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('profile.show');
        Route::post('/update', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::post('/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    });
    Route::prefix('themes')->group(function () {
        Route::get('/', [ThemeController::class, 'index'])->name('themes.index');
        Route::post('/', [ThemeController::class, 'store'])->name('themes.store');
        Route::prefix('/{id}')->group(function () {
            Route::get('', [ThemeController::class, 'show'])->name('themes.show');
            Route::put('', [ThemeController::class, 'update'])->name('themes.update');
            Route::delete('', [ThemeController::class, 'destroy'])->name('themes.destroy');
            Route::get('/stats', [StatsController::class, 'themeStats'])->name('stats.theme');
            Route::prefix('/members')->group(function () {
                Route::get('', [ThemeMemberController::class, 'listMembers'])->name('theme.members.list');
                Route::post('', [ThemeMemberController::class, 'inviteUser'])->name('theme.members.invite');
                Route::prefix('/{userId}')->group(function () {
                    Route::put('', [ThemeMemberController::class, 'updateMemberPermissions'])->name('theme.members.update');
                    Route::delete('', [ThemeMemberController::class, 'removeMember'])->name('theme.members.remove');
                    Route::post('/deactivate', [ThemeMemberController::class, 'deactivateMember'])->name('theme.members.deactivate');
                    Route::post('/reactivate', [ThemeMemberController::class, 'reactivateMember'])->name('theme.members.reactivate');
                });
            });
            Route::post('/leave', [ThemeMemberController::class, 'leaveTheme'])->name('theme.leave');
        });
    });
    Route::prefix('tasks')->group(function () {
        Route::get('/', [TaskController::class, 'index'])->name('tasks.index');
        Route::post('/', [TaskController::class, 'store'])->name('tasks.store');
        Route::prefix('/{id}')->group(function () {
            Route::get('', [TaskController::class, 'show'])->name('tasks.show');
            Route::put('', [TaskController::class, 'update'])->name('tasks.update');
            Route::delete('', [TaskController::class, 'destroy'])->name('tasks.destroy');
            Route::post('/archive', [TaskController::class, 'archive'])->name('tasks.archive');
            Route::post('/restore', [TaskController::class, 'restore'])->name('tasks.restore');
            Route::post('/complete', [TaskController::class, 'complete'])->name('tasks.complete');
            Route::post('/uncomplete', [TaskController::class, 'uncomplete'])->name('tasks.uncomplete');
        });
    });
    Route::post('/email/verification-notification', EmailVerificationNotificationController::class)
        ->middleware('throttle:6,1')
        ->name('verification.send');
    Route::get('/user', UserController::class)
        ->name('user');
});
