<?php

namespace App\Http\Middleware;

use App\Support\Auth\TokenService;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccessToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->tokenCan(TokenService::ACCESS_ABILITY)) {
            throw new AuthorizationException('Access token required');
        }

        return $next($request);
    }
}
