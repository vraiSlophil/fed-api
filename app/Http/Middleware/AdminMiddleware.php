<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Ensure the request is authenticated and the user has admin-level role power.
     *
     * @param  Request  $request  Current HTTP request being authorized for admin-only routes.
     * @param  Closure  $next  Callback that advances the middleware pipeline on success.
     * @return Response Response returned by the downstream middleware stack.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException When the operation cannot be completed.
     */
    public function handle(Request $request, Closure $next)
    {
        if (! auth()->check() || auth()->user()->role_power < 100) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
