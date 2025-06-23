<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    /**
     * Réponse standard en cas de succès.
     */
    public static function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /**
     * Réponse standard en cas d’erreur.
     */
    public static function error(
        string $message = 'Une erreur est survenue',
        int $status = 400,
        mixed $errors = null
    ): JsonResponse {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }
}
