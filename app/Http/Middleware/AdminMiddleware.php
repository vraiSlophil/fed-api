<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check() || auth()->user()->role_power < 100) {
            return ApiResponse::error('Accès refusé. Privilèges administrateur requis.', 403);
        }

        return $next($request);
    }
}
