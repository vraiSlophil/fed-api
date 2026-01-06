<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Auth\Access\AuthorizationException;

class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() ||
            ($request->user() instanceof MustVerifyEmail &&
                !$request->user()->hasVerifiedEmail())) {
            throw new AuthorizationException('Email not verified');
        }

        return $next($request);
    }
}
