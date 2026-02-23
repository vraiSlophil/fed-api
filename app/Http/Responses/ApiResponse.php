<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class ApiResponse
{
    public static function builder(): ApiResponseBuilder
    {
        return new ApiResponseBuilder;
    }

    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $status = 200,
        ?string $messageCode = 'common.ok',
        array $messageParams = []
    ): JsonResponse {
        return self::builder()
            ->success($status, $message ?? 'Ok')
            ->messageCode($messageCode, $messageParams)
            ->data($data)
            ->json();
    }

    public static function error(
        ?string $message = null,
        int $status = 400,
        mixed $errors = null,
        ?string $messageCode = 'common.error',
        array $messageParams = []
    ): JsonResponse {
        return self::builder()
            ->error($status, $message)
            ->messageCode($messageCode, $messageParams)
            ->errors($errors)
            ->json();
    }

    public static function noContent(int $status = 204, array $headers = []): Response
    {
        return response()->noContent($status, $headers);
    }

    public static function media(): ApiMediaBuilder
    {
        return new ApiMediaBuilder;
    }
}
