<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AttachRequestId;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\LogHttpRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustHosts(at: fn () => config('app.trusted_hosts'));

        // API = stateless : pas de cookies Sanctum stateful, seulement Bearer tokens.
        $middleware->api(prepend: [
            AttachRequestId::class,
        ]);

        $middleware->alias([
            EnsureEmailIsVerified::class,
        ]);

        $middleware->alias([
            'admin' => AdminMiddleware::class,
        ]);

        $middleware->append(LogHttpRequests::class);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
