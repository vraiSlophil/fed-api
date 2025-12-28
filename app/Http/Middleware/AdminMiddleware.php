<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (! auth()->check() || auth()->user()->role_power < 100) {
            return ApiResponse::builder()
                ->error(403, 'Accès refusé. Privilèges administrateur requis.')
                ->json();
        }

        return $next($request);
    }
}
