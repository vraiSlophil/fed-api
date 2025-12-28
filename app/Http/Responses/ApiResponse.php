<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * ApiResponse facade + Builder implementations.
 *
 * Usage examples:
 *  ApiResponse::builder()->success()->message('ok')->data($payload)->header('X-Foo','bar')->json();
 *  ApiResponse::builder()->error(422)->message('Invalid')->errors($validation)->json();
 *  ApiResponse::media()->path($file)->filename('export.pdf')->attachment()->build();
 */
final class ApiResponse
{
    /**
     * Create a new generic response builder.
     */
    public static function builder(): ApiResponseBuilder
    {
        return new ApiResponseBuilder;
    }

    /**
     * Convenience shortcuts for the common patterns.
     */
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return self::builder()
            ->success($status, $message)
            ->data($data)
            ->json();
    }

    public static function error(string $message = 'Une erreur est survenue', int $status = 400, mixed $errors = null): JsonResponse
    {
        return self::builder()
            ->error($status, $message)
            ->errors($errors)
            ->json();
    }

    /**
     * Media response builder (files).
     */
    public static function media(): ApiMediaBuilder
    {
        return new ApiMediaBuilder;
    }
}
