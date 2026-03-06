<?php

namespace App\Http\Middleware;

use App\Domain\Auth\Services\TokenService;
use App\Exceptions\ApiException;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccessToken
{
    /**
     * Ensure the authenticated request is backed by an access token ability.
     *
     * @param  Request  $request  Current HTTP request expected to contain an authenticated user token.
     * @param  Closure  $next  Callback that advances the middleware pipeline.
     * @return Response Response returned by downstream middleware/controller.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException When the operation cannot be completed.
     * @throws \App\Exceptions\ApiException When the user is blocked.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            throw new AuthorizationException('Access token required');
        }

        if ($user->isBlocked()) {
            throw new ApiException('auth.blocked', [], 403, 'User blocked');
        }

        if (! $user->tokenCan(TokenService::ACCESS_ABILITY)) {
            throw new AuthorizationException('Access token required');
        }

        return $next($request);
    }
}
