<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RefreshTokenController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PlaygroundController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\ThemeInvitationController;
use App\Http\Controllers\ThemeMemberController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserMetricsController;
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

Route::prefix('/auth')->group(function () {
    Route::post('/register', RegisterController::class)->name('auth.register');
    Route::post('/login', LoginController::class)
        ->middleware('throttle:auth-login')
        ->name('auth.login');
    Route::post('/refresh', RefreshTokenController::class)
        ->middleware('throttle:auth-refresh')
        ->name('auth.refresh');
    Route::post('/forgot-password', PasswordResetLinkController::class)->name('auth.password.email');
    Route::post('/reset-password', NewPasswordController::class)->name('auth.password.store');
});

// Vérification d’e-mail via URL signée (stateless, JSON only)
Route::post('/email-verifications', VerifyEmailController::class)
    ->middleware(['signed:relative', 'throttle:6,1'])
    ->name('verification.verify');

Route::get('/media/{path}', [MediaController::class, 'show'])
    ->where('path', '.*')
    ->name('media.show');

/*
|----------------------------------------------------------------------
| Routes protégées par jeton Sanctum uniquement
|----------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'access-token'])->group(function () {
    Route::prefix('/auth')->group(function () {

        Route::post('/logout', LogoutController::class)
            ->name('logout');
        Route::get('/ping', function () {
            return ApiResponse::builder()
                ->success(message: 'pong')
                ->json();
        })->name('ping');
    });
    Route::post('/email-verification-notifications', EmailVerificationNotificationController::class)
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Invitations via URL signée (auth + signature)
    Route::patch('/invitations/{invitation}', [ThemeInvitationController::class, 'respond'])
        ->middleware(['signed:relative', 'throttle:6,1'])
        ->name('invitations.respond');
});

/*
|----------------------------------------------------------------------
| Routes protégées par jeton Sanctum et vérification d’e-mail
|----------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'access-token', 'verified'])->group(function () {

    Route::get('/stats', [StatsController::class, 'globalStats'])->name('stats.global');
    Route::get('/users/search', [ThemeMemberController::class, 'searchUsers'])->name('users.search');

    Route::prefix('/user')->group(function () {
        Route::get('', UserController::class)->name('user.show');
        Route::get('/metrics', [UserMetricsController::class, 'getUserMetrics'])->name('user.metrics');
    });

    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('profile.show');
        Route::post('/update', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::post('/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    });

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::prefix('users')->group(function () {
            Route::get('/', [AdminUserController::class, 'index'])->name('admin.users.index');
            Route::post('/', [AdminUserController::class, 'store'])->name('admin.users.store');
            Route::prefix('/{user}')->group(function () {
                Route::get('', [AdminUserController::class, 'show'])->name('admin.users.show');
                Route::post('', [AdminUserController::class, 'update'])->name('admin.users.update');
                Route::delete('', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
                Route::post('/block', [AdminUserController::class, 'block'])->name('admin.users.block');
                Route::post('/unblock', [AdminUserController::class, 'unblock'])->name('admin.users.unblock');
                //                Route::get('/metrics', [UserMetricsController::class, 'getAdminUserMetrics'])->name('admin.users.metrics');
            });
        });
    });

    Route::prefix('playgrounds')->group(function () {
        Route::get('/', [PlaygroundController::class, 'index']);
        Route::post('/', [PlaygroundController::class, 'store']);

        // Accès par ID
        Route::prefix('/{playgroundId}')->group(function () {
            Route::get('', [PlaygroundController::class, 'show']);
            Route::get('/themes', [PlaygroundController::class, 'themes']);
            Route::patch('', [PlaygroundController::class, 'update']);
            Route::delete('', [PlaygroundController::class, 'destroy']);
            Route::post('/set-default', [PlaygroundController::class, 'setAsDefault']);
            Route::get('/stats', [PlaygroundController::class, 'stats']);
        })->whereUuid('playgroundId');

        // Accès par slug
        Route::prefix('/by-slug/{slug}')->group(function () {
            Route::get('', [PlaygroundController::class, 'showBySlug']);
            Route::get('/themes', [PlaygroundController::class, 'themesBySlug']);
        });
    });

    Route::prefix('themes')->group(function () {
        Route::get('/', [ThemeController::class, 'index'])->name('themes.index');
        Route::post('/', [ThemeController::class, 'store'])->name('themes.store');
        Route::prefix('/{id}')->group(function () {
            Route::get('', [ThemeController::class, 'show'])->name('themes.show');
            Route::patch('', [ThemeController::class, 'update'])->name('themes.update');
            Route::delete('', [ThemeController::class, 'destroy'])->name('themes.destroy');
            Route::get('/stats', [StatsController::class, 'themeStats'])->name('stats.theme');
            Route::prefix('/members')->group(function () {
                Route::get('', [ThemeMemberController::class, 'listMembers'])->name('theme.members.list');
                Route::post('', [ThemeMemberController::class, 'inviteUser'])->name('theme.members.invite');
                Route::prefix('/{userId}')->group(function () {
                    Route::patch('', [ThemeMemberController::class, 'updateMemberPermissions'])->name('theme.members.update');
                    Route::delete('', [ThemeMemberController::class, 'removeMember'])->name('theme.members.remove');
                    Route::post('/deactivate', [ThemeMemberController::class, 'deactivateMember'])->name('theme.members.deactivate');
                    Route::post('/reactivate', [ThemeMemberController::class, 'reactivateMember'])->name('theme.members.reactivate');
                    Route::patch('/move-to-playground', [ThemeMemberController::class, 'moveToPlayground'])->name('theme.members.move-to-playground');
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
            Route::patch('', [TaskController::class, 'update'])->name('tasks.update');
            Route::delete('', [TaskController::class, 'destroy'])->name('tasks.destroy');
            Route::post('/archive', [TaskController::class, 'archive'])->name('tasks.archive');
            Route::post('/restore', [TaskController::class, 'restore'])->name('tasks.restore');
            Route::post('/complete', [TaskController::class, 'complete'])->name('tasks.complete');
            Route::post('/uncomplete', [TaskController::class, 'uncomplete'])->name('tasks.uncomplete');
        });
    });
});
