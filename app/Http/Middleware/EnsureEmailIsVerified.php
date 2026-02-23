<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * Ensure the authenticated user has a verified email before route execution.
     *
     * @param  Request  $request  Current HTTP request expected to include an authenticated user.
     * @param  Closure  $next  Callback that advances the middleware pipeline.
     * @return Response Response returned by downstream middleware/controller.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException When the operation cannot be completed.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() ||
            ($request->user() instanceof MustVerifyEmail &&
                ! $request->user()->hasVerifiedEmail())) {
            throw new AuthorizationException('Email not verified');
        }

        return $next($request);
    }
}
