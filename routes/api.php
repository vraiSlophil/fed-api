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
use App\Http\Controllers\Invitations\ThemeInvitationController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\Metrics\StatsController;
use App\Http\Controllers\Metrics\UserMetricsController;
use App\Http\Controllers\Playgrounds\PlaygroundController;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\Tasks\TaskController;
use App\Http\Controllers\ThemeMembers\ThemeMemberController;
use App\Http\Controllers\Themes\ThemeController;
use App\Http\Controllers\Users\UserController;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Route;

/*
|-----------------------------------------------------------------------
| API Routes
|-----------------------------------------------------------------------
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

Route::post('/email-verifications', VerifyEmailController::class)
    ->middleware(['signed:relative', 'throttle:6,1'])
    ->name('verification.verify');

Route::get('/media/{path}', [MediaController::class, 'show'])
    ->where('path', '.*')
    ->name('media.show');

Route::middleware(['auth:sanctum', 'access-token'])->group(function () {
    Route::prefix('/auth')->group(function () {
        Route::post('/logout', LogoutController::class)->name('logout');
        Route::get('/ping', function () {
            return ApiResponse::builder()
                ->success(message: 'pong')
                ->json();
        })->name('ping');
    });

    Route::post('/email-verification-notifications', EmailVerificationNotificationController::class)
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::patch('/invitations/{invitation:invitation_id}', [ThemeInvitationController::class, 'respond'])
        ->middleware(['signed:relative', 'throttle:6,1', 'can:respond,invitation'])
        ->name('invitations.respond');
});

Route::middleware(['auth:sanctum', 'access-token', 'verified'])->group(function () {
    Route::get('/invitations', [ThemeInvitationController::class, 'index'])->name('invitations.index');

    Route::get('/stats', [StatsController::class, 'globalStats'])->name('stats.global');
    Route::get('/users', [AdminUserController::class, 'index'])->middleware('admin')->name('users.index');
    Route::get('/users/me', UserController::class)->name('users.me');
    Route::patch('/users/{user:user_id}', [AdminUserController::class, 'update'])
        ->whereUuid('user')
        ->middleware('can:update,user')
        ->name('users.update');
    Route::get('/user/metrics', [UserMetricsController::class, 'getUserMetrics'])->name('user.metrics');

    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('profile.show');
    });

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::prefix('users')->group(function () {
            Route::get('/', [AdminUserController::class, 'index'])->name('admin.users.index');
            Route::post('/', [AdminUserController::class, 'store'])->name('admin.users.store');
            Route::prefix('/{user:user_id}')->group(function () {
                Route::get('', [AdminUserController::class, 'show'])->middleware('can:view,user')->name('admin.users.show');
                Route::delete('', [AdminUserController::class, 'destroy'])->middleware('can:delete,user')->name('admin.users.destroy');
                Route::post('/block', [AdminUserController::class, 'block'])->middleware('can:block,user')->name('admin.users.block');
                Route::post('/unblock', [AdminUserController::class, 'unblock'])->middleware('can:unblock,user')->name('admin.users.unblock');
            });
        });
    });

    Route::prefix('playgrounds')->group(function () {
        Route::get('/', [PlaygroundController::class, 'index']);
        Route::post('/', [PlaygroundController::class, 'store']);

        Route::prefix('/{playground:playground_id}')->group(function () {
            Route::get('', [PlaygroundController::class, 'show'])->middleware('can:view,playground');
            Route::get('/themes', [PlaygroundController::class, 'themes'])->middleware('can:view,playground');
            Route::patch('', [PlaygroundController::class, 'update'])->middleware('can:update,playground');
            Route::delete('', [PlaygroundController::class, 'destroy'])->middleware('can:delete,playground');
            Route::post('/set-default', [PlaygroundController::class, 'setAsDefault'])->middleware('can:setDefault,playground');
            Route::get('/stats', [PlaygroundController::class, 'stats'])->middleware('can:stats,playground');
        });

        Route::prefix('/by-slug/{playground:slug}')->group(function () {
            Route::get('', [PlaygroundController::class, 'showBySlug'])->middleware('can:view,playground');
            Route::get('/themes', [PlaygroundController::class, 'themesBySlug'])->middleware('can:view,playground');
        });
    });

    Route::prefix('themes')->group(function () {
        Route::get('/', [ThemeController::class, 'index'])->name('themes.index');
        Route::post('/', [ThemeController::class, 'store'])->name('themes.store');

        Route::prefix('/{theme:theme_id}')->group(function () {
            Route::get('', [ThemeController::class, 'show'])->middleware('can:view,theme')->name('themes.show');
            Route::patch('', [ThemeController::class, 'update'])->middleware('can:update,theme')->name('themes.update');
            Route::delete('', [ThemeController::class, 'destroy'])->middleware('can:delete,theme')->name('themes.destroy');
            Route::get('/stats', [StatsController::class, 'themeStats'])->middleware('can:view,theme')->name('stats.theme');

            Route::prefix('/members')->group(function () {
                Route::get('', [ThemeMemberController::class, 'listMembers'])->middleware('can:manageMembers,theme')->name('theme.members.list');
                Route::post('', [ThemeMemberController::class, 'inviteUser'])->middleware('can:manageMembers,theme')->name('theme.members.invite');
                Route::prefix('/{userId}')->group(function () {
                    Route::patch('', [ThemeMemberController::class, 'updateMemberPermissions'])->middleware('can:manageMembers,theme')->name('theme.members.update');
                    Route::delete('', [ThemeMemberController::class, 'removeMember'])->middleware('can:manageMembers,theme')->name('theme.members.remove');
                    Route::post('/deactivate', [ThemeMemberController::class, 'deactivateMember'])->middleware('can:manageMembers,theme')->name('theme.members.deactivate');
                    Route::post('/reactivate', [ThemeMemberController::class, 'reactivateMember'])->middleware('can:manageMembers,theme')->name('theme.members.reactivate');
                    Route::patch('/move-to-playground', [ThemeMemberController::class, 'moveToPlayground'])->middleware('can:view,theme')->name('theme.members.move-to-playground');
                });
            });

            Route::post('/leave', [ThemeMemberController::class, 'leaveTheme'])->middleware('can:view,theme')->name('theme.leave');
        });
    });

    Route::prefix('tasks')->group(function () {
        Route::get('/', [TaskController::class, 'index'])->name('tasks.index');
        Route::post('/', [TaskController::class, 'store'])->name('tasks.store');

        Route::prefix('/{task:task_id}')->group(function () {
            Route::get('', [TaskController::class, 'show'])->middleware('can:view,task')->name('tasks.show');
            Route::patch('', [TaskController::class, 'update'])->middleware('can:update,task')->name('tasks.update');
            Route::delete('', [TaskController::class, 'destroy'])->middleware('can:delete,task')->name('tasks.destroy');
            Route::post('/archive', [TaskController::class, 'archive'])->middleware('can:archive,task')->name('tasks.archive');
            Route::post('/restore', [TaskController::class, 'restore'])->middleware('can:restore,task')->name('tasks.restore');
            Route::post('/complete', [TaskController::class, 'complete'])->middleware('can:validate,task')->name('tasks.complete');
            Route::post('/uncomplete', [TaskController::class, 'uncomplete'])->middleware('can:validate,task')->name('tasks.uncomplete');
        });
    });
});
