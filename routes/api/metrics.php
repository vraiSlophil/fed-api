<?php

use App\Http\Controllers\Metrics\StatsController;
use App\Http\Controllers\Metrics\UserMetricsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'access-token', 'verified'])->group(function () {
    Route::get('/stats', [StatsController::class, 'globalStats'])->name('stats.global');
    Route::get('/user/metrics', [UserMetricsController::class, 'getUserMetrics'])->name('user.metrics');
});
