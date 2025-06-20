<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\UserController;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

// -------------------------------------------------
// API Routes
// -------------------------------------------------
Route::get('/ping', function () {
    return ApiResponse::success(null, 'Pong', 200);
});

// -------------------------------------------------
// Authentication Routes
// -------------------------------------------------
Route::middleware(['guest'])->group(function () {
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/register', [RegisterController::class, 'register']);
});


Route::group((array)'', function () {
    Route::post('/logout', [LoginController::class, 'logout']);
})->middleware(['auth:sanctum']);

Route::get('/user', function (Request $request) {
    return $request;
})->middleware(['auth:sanctum']);
