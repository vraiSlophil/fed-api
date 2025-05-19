<?php

use App\Http\Responses\ApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        /*
        |------------------------------------------------------------------
        | Rendu JSON personnalisé
        |------------------------------------------------------------------
        */
        $exceptions->renderable(function (ValidationException $e, Request $request) {
            return ApiResponse::error(
                'Les données fournies ne sont pas valides',
                422,
                $e->errors()
            );
        });

        $exceptions->renderable(function (Throwable $e, $request) {
            $status = $e instanceof HttpExceptionInterface
                ? $e->getStatusCode()
                : 500;

            return ApiResponse::error(
                $status >= 500 ? 'Une erreur interne est survenue'
                    : $e->getMessage(),
                $status,
                app()->hasDebugModeEnabled() ? ['trace' => $e->getTrace()] : null
            );
        });
    })
    ->create();
