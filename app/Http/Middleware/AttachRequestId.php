<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AttachRequestId
{
    /**
     * Attach a request identifier to request attributes and response metadata/headers.
     *
     * @param  Request  $request  Current HTTP request that may already include `X-Request-Id`.
     * @param  Closure  $next  Callback that advances the middleware pipeline.
     * @return Response Response enriched with the request identifier header and optional JSON metadata.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->headers->get('X-Request-Id') ?: (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        if ($response instanceof JsonResponse) {
            $payload = $response->getData(true);
            if (is_array($payload)) {
                $meta = $payload['meta'] ?? [];
                if (! is_array($meta)) {
                    $meta = [];
                }

                $payload['meta'] = array_merge($meta, [
                    'request_id' => $requestId,
                ]);
                $response->setData($payload);
            }
        }

        return $response;
    }
}
