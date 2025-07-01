<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
     * Réponse standard en cas d'erreur.
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
    
    /**
     * Réponse pour les fichiers médias.
     */
    public static function media(
        string $path,
        string $mimeType = null,
        string $filename = null,
        array $headers = []
    ): BinaryFileResponse|Response {
        if (!file_exists($path)) {
            return response('', 404);
        }
        
        $response = response()->file($path, array_merge([
            'Content-Type' => $mimeType ?: mime_content_type($path),
        ], $headers));
        
        if ($filename) {
            $response->setContentDisposition('inline', $filename);
        }
        
        return $response;
    }
}