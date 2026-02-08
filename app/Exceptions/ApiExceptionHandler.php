<?php

namespace App\Exceptions;

use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ApiExceptionHandler
{
    public function register(Exceptions $exceptions): void
    {
        $levelForStatus = static function (?int $status): string {
            if ($status === null) {
                return 'error';
            }
            if ($status >= 500) {
                return 'error';
            }
            if ($status === 404) {
                return 'info';
            }

            return 'warning';
        };

        $getRequestId = static function ($request): string {
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
        };

        $channelForLevel = static function (string $level): string {
            return $level === 'error' ? 'errors' : 'warnings';
        };

        $logException = static function (Throwable $e, string $requestId, $request, string $level) use ($channelForLevel): void {
            try {
                Log::channel($channelForLevel($level))->log($level, 'API exception', [
                    'request_id' => $requestId,
                    'exception_class' => get_class($e),
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                ]);
            } catch (Throwable $logFailure) {
                // Never throw from exception logging to avoid recursive failure loops.
                error_log(sprintf(
                    '[api-exception-log-failure] request_id=%s original=%s logger_error=%s',
                    $requestId,
                    get_class($e),
                    $logFailure->getMessage()
                ));
            }
        };

        // Always return the API envelope for /api/* routes, even without Accept: application/json.
        $shouldRenderJson = static function ($request): bool {
            return $request->expectsJson() || $request->is('api/*');
        };

        // Stop Laravel's default reporter for known 4xx/expected cases.
        $exceptions->reportable(function (ApiException $e): bool {
            return false;
        });

        $exceptions->reportable(function (ValidationException $e): bool {
            return false;
        });

        $exceptions->reportable(function (AuthenticationException $e): bool {
            return false;
        });

        $exceptions->reportable(function (AuthorizationException|AccessDeniedHttpException $e): bool {
            return false;
        });

        $exceptions->reportable(function (InvalidSignatureException $e): bool {
            return false;
        });

        $exceptions->reportable(function (ModelNotFoundException|NotFoundHttpException $e): bool {
            return false;
        });

        $exceptions->renderable(function (ApiException $e, $request) use ($getRequestId, $logException, $levelForStatus, $shouldRenderJson) {
            $requestId = $getRequestId($request);
            $logException($e, $requestId, $request, $levelForStatus($e->status));

            if (! $shouldRenderJson($request)) {
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

        $exceptions->renderable(function (ValidationException $e, $request) use ($getRequestId, $logException, $shouldRenderJson): ?\Illuminate\Http\JsonResponse {
            $requestId = $getRequestId($request);
            $logException($e, $requestId, $request, 'warning');

            if (! $shouldRenderJson($request)) {
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

        $exceptions->renderable(function (AuthenticationException $e, $request) use ($getRequestId, $logException, $shouldRenderJson): ?\Illuminate\Http\JsonResponse {
            $requestId = $getRequestId($request);
            $logException($e, $requestId, $request, 'warning');

            if (! $shouldRenderJson($request)) {
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

        $exceptions->renderable(function (AuthorizationException|AccessDeniedHttpException $e, $request) use ($getRequestId, $logException, $shouldRenderJson): ?\Illuminate\Http\JsonResponse {
            $requestId = $getRequestId($request);
            $logException($e, $requestId, $request, 'warning');

            if (! $shouldRenderJson($request)) {
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

        $exceptions->renderable(function (InvalidSignatureException $e, $request) use ($getRequestId, $logException, $shouldRenderJson): ?\Illuminate\Http\JsonResponse {
            $requestId = $getRequestId($request);
            $logException($e, $requestId, $request, 'warning');

            if (! $shouldRenderJson($request)) {
                return null;
            }

            $response = ApiResponse::builder()
                ->error(403, 'Invalid signature')
                ->messageCode('signature.invalid')
                ->meta(['request_id' => $requestId])
                ->build();

            $response->headers->set('X-Request-Id', $requestId);

            return $response;
        });

        $exceptions->renderable(function (ModelNotFoundException|NotFoundHttpException $e, $request) use ($getRequestId, $logException, $shouldRenderJson): ?\Illuminate\Http\JsonResponse {
            $requestId = $getRequestId($request);
            $logException($e, $requestId, $request, 'info');

            if (! $shouldRenderJson($request)) {
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

        $exceptions->renderable(function (Throwable $e, $request) use ($getRequestId, $logException, $shouldRenderJson): ?\Illuminate\Http\JsonResponse {
            $requestId = $getRequestId($request);
            $logException($e, $requestId, $request, 'error');

            if (! $shouldRenderJson($request)) {
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
}
