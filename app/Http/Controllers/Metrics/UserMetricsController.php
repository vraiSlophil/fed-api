<?php

namespace App\Http\Controllers\Metrics;

use App\Domain\Metrics\Queries\UserMetricsQueryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Metrics\UserMetricsRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class UserMetricsController extends Controller
{
    /**
     * Initialize the controller with analytics query handlers.
     *
     * @param  UserMetricsQueryService  $queryService  Service that aggregates per-user analytics metrics.
     */
    public function __construct(private readonly UserMetricsQueryService $queryService) {}

    /**
     * Return analytics metrics for the authenticated user and requested period.
     *
     * @param  UserMetricsRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function getUserMetrics(UserMetricsRequest $request): JsonResponse
    {
        $period = (string) ($request->validated('period') ?? '12_months');
        $metrics = $this->queryService->metricsFor($request->user(), $period);

        return ApiResponse::builder()
            ->success()
            ->messageCode('user.metrics.retrieved', ['period' => $period])
            ->data($metrics)
            ->json();
    }
}
