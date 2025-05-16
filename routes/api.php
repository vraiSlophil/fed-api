<?php

use Illuminate\Support\Facades\Route;

Route::middleware('api')->group(function () {
    Route::get('/ping', function () {
        return response()->json(['message' => 'API is running']);
    });
});
