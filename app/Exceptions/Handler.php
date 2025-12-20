<?php

namespace App\Exceptions;

use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class Handler extends ExceptionHandler
{
    public function register(): void
    {
        $this->renderable(function (ApiException $e, $request) {
            if (!$request->expectsJson()) {
                return null;
            }

            return ApiResponse::error(
                message: $e->getMessage(),
                status: $e->status,
                errors: null,
                messageCode: $e->messageCode,
                messageParams: $e->messageParams
            );
        });

        $this->renderable(function (ValidationException $e, $request) {
            if (!$request->expectsJson()) {
                return null;
            }

            return ApiResponse::error(
                message: 'Validation failed',
                status: 422,
                errors: $e->errors(),
                messageCode: 'validation.invalid',
                messageParams: []
            );
        });

        $this->renderable(function (AuthenticationException $e, $request) {
            if (!$request->expectsJson()) {
                return null;
            }

            return ApiResponse::error(
                message: 'Authentication required',
                status: 401,
                errors: null,
                messageCode: 'auth.failed'
            );
        });

        $this->renderable(function (AuthorizationException $e, $request) {
            if (!$request->expectsJson()) {
                return null;
            }

            return ApiResponse::error(
                message: 'Forbidden',
                status: 403,
                errors: null,
                messageCode: 'permission.denied'
            );
        });

        $this->renderable(function (ModelNotFoundException|NotFoundHttpException $e, $request) {
            if (!$request->expectsJson()) {
                return null;
            }

            return ApiResponse::error(
                message: 'Not found',
                status: 404,
                errors: null,
                messageCode: 'resource.not_found'
            );
        });

        $this->renderable(function (Throwable $e, $request) {
            if (!$request->expectsJson()) {
                return null;
            }

            $isProd = app()->environment('production');

            return ApiResponse::error(
                message: $isProd ? 'Server error' : ($e->getMessage() ?: 'Server error'),
                status: 500,
                errors: $isProd ? null : ['exception' => get_class($e)],
                messageCode: 'common.error'
            );
        });
    }
}
