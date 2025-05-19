<?php

use App\Http\Responses\ApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

        $exceptions->renderable(function (Throwable $e, Request $request) {
            // Réponse générique pour toute autre exception
            return ApiResponse::error(
                'Une erreur interne est survenue',
                500,
                app()->hasDebugModeEnabled()
                    ? ['message' => $e->getMessage()]
                    : null
            );
        });
    })
    ->create();
