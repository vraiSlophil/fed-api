<?php

use App\Exceptions\ApiException;
use App\Http\Controllers\Metrics\StatsController;
use App\Http\Controllers\ThemeMembers\ThemeMemberController;
use App\Http\Controllers\Themes\ThemeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'access-token', 'verified'])->group(function () {
    Route::prefix('themes')->group(function () {
        Route::get('', [ThemeController::class, 'index'])->name('themes.index');
        Route::post('', [ThemeController::class, 'store'])->name('themes.store');

        Route::prefix('{theme:theme_id}')->whereUuid('theme')->group(function () {
            Route::get('', [ThemeController::class, 'show'])->middleware('can:view,theme')->name('themes.show');
            Route::patch('', [ThemeController::class, 'update'])->middleware('can:update,theme')->name('themes.update');
            Route::delete('', [ThemeController::class, 'destroy'])->middleware('can:delete,theme')->name('themes.destroy');
            Route::get('stats', [StatsController::class, 'themeStats'])->middleware('can:view,theme')->name('stats.theme');

            Route::prefix('members')->name('theme.members.')->group(function () {
                Route::get('', [ThemeMemberController::class, 'listMembers'])->middleware('can:manageMembers,theme')->name('list');
                Route::post('', function () {
                    throw new ApiException('resource.not_found', [], 404, 'Not found');
                })->name('store');
                Route::prefix('{userId}')->whereUuid('userId')->group(function () {
                    Route::patch('', [ThemeMemberController::class, 'updateMemberPermissions'])
                        ->name('update');
                    Route::delete('', [ThemeMemberController::class, 'removeMember'])
                        ->name('remove');
                });
            });
        });
    });
});
