<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

// -------------------------------------------------
// API Routes
// -------------------------------------------------
Route::get('/ping', function () {
    return response()->json(['message' => 'API is running']);
});

// -------------------------------------------------
// Authentication Routes
// -------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/register', [RegisterController::class, 'register']);
});

// -------------------------------------------------
// Protected Routes
// -------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return response()->json($request->user());
    });
});
