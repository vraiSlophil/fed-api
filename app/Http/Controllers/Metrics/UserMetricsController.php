<?php

namespace App\Http\Controllers\Metrics;

use App\Domain\Metrics\Queries\UserMetricsQueryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Metrics\UserMetricsRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class UserMetricsController extends Controller
{
    public function __construct(private readonly UserMetricsQueryService $queryService) {}

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
