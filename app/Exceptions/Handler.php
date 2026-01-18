<?php

namespace App\Exceptions;

use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class Handler extends ExceptionHandler
{
    public function register(): void
    {
        $this->renderable(function (ApiException $e, $request) {
            $requestId = $this->getOrCreateRequestId($request);
            $this->logException($e, $requestId, $request);

            if (! $request->expectsJson()) {
                return null;
            }

            $response = ApiResponse::builder()
                ->error($e->status)
                ->messageCode($e->messageCode, $e->messageParams)
                ->meta(['request_id' => $requestId])
                ->build();

            $response->headers->set('X-Request-Id', $requestId);

            return $response;
        });

        $this->renderable(function (ValidationException $e, $request) {
            $requestId = $this->getOrCreateRequestId($request);
            $this->logException($e, $requestId, $request);

            if (! $request->expectsJson()) {
                return null;
            }

            $response = ApiResponse::builder()
                ->error(422, 'Validation failed')
                ->messageCode('validation.invalid')
                ->meta(['request_id' => $requestId])
                ->errors($e->errors())
                ->build();

            $response->headers->set('X-Request-Id', $requestId);

            return $response;
        });

        $this->renderable(function (AuthenticationException $e, $request) {
            $requestId = $this->getOrCreateRequestId($request);
            $this->logException($e, $requestId, $request);

            if (! $request->expectsJson()) {
                return null;
            }

            $response = ApiResponse::builder()
                ->error(401, 'Authentication required')
                ->messageCode('auth.failed')
                ->meta(['request_id' => $requestId])
                ->build();

            $response->headers->set('X-Request-Id', $requestId);

            return $response;
        });

        $this->renderable(function (AuthorizationException $e, $request) {
            $requestId = $this->getOrCreateRequestId($request);
            $this->logException($e, $requestId, $request);

            if (! $request->expectsJson()) {
                return null;
            }

            $response = ApiResponse::builder()
                ->error(403, 'Forbidden')
                ->messageCode('permission.denied')
                ->meta(['request_id' => $requestId])
                ->build();

            $response->headers->set('X-Request-Id', $requestId);

            return $response;
        });

        $this->renderable(function (ModelNotFoundException|NotFoundHttpException $e, $request) {
            $requestId = $this->getOrCreateRequestId($request);
            $this->logException($e, $requestId, $request, level: 'info');

            if (! $request->expectsJson()) {
                return null;
            }

            $response = ApiResponse::builder()
                ->error(404, 'Not found')
                ->messageCode('resource.not_found')
                ->meta(['request_id' => $requestId])
                ->build();

            $response->headers->set('X-Request-Id', $requestId);

            return $response;
        });

        $this->renderable(function (Throwable $e, $request) {
            $requestId = $this->getOrCreateRequestId($request);
            $this->logException($e, $requestId, $request, level: 'error');

            if (! $request->expectsJson()) {
                return null;
            }

            $response = ApiResponse::builder()
                ->error(500, 'Server error')
                ->messageCode('common.error')
                ->meta(['request_id' => $requestId])
                ->build();

            $response->headers->set('X-Request-Id', $requestId);

            return $response;
        });
    }

    private function getOrCreateRequestId($request): string
    {
        $existing = $request->attributes->get('request_id');
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $header = $request->headers->get('X-Request-Id');
        if (is_string($header) && $header !== '') {
            $request->attributes->set('request_id', $header);

            return $header;
        }

        $requestId = (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);

        return $requestId;
    }

    private function logException(Throwable $e, string $requestId, $request, string $level = 'error'): void
    {
        $context = [
            'request_id' => $requestId,
            'exception_class' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
        ];

        Log::channel('single')->log($level, 'API exception', $context);
    }
}
