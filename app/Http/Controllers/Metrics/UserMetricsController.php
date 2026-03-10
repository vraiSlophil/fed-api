<?php

namespace App\Http\Controllers\Metrics;

use App\Domain\Metrics\Queries\UserMetricsQueryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Metrics\UserMetricsRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Metrics endpoints for authenticated users.
 *
 * @group Metrics
 */
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
     *
     * @response 200 {
     *   "status": "success",
     *   "message": "Ok",
     *   "message_code": "user.metrics.retrieved",
     *   "data": {
     *     "overview": {
     *       "total_themes_owned": 4,
     *       "total_themes_member": 2,
     *       "total_tasks_created": 32,
     *       "total_tasks_completed": 19,
     *       "completion_rate": 59.38
     *     },
     *     "themes_over_time": {
     *       "data": [
     *         {
     *           "date": "2026-03-01",
     *           "count": 1
     *         }
     *       ],
     *       "total_in_period": 1,
     *       "average_per_day": 0.1
     *     },
     *     "tasks_over_time": {
     *       "created": {
     *         "data": [],
     *         "total": 0
     *       },
     *       "completed": {
     *         "data": [],
     *         "total": 0
     *       }
     *     },
     *     "activity_metrics": {
     *       "active_days_count": 3,
     *       "active_days": ["2026-03-01", "2026-03-04", "2026-03-09"],
     *       "current_streak": 1,
     *       "longest_streak": 2,
     *       "activity_percentage": 30
     *     },
     *     "productivity_trends": {
     *       "weekly": {
     *         "current": 2,
     *         "previous": 5,
     *         "trend": -60
     *       },
     *       "monthly": {
     *         "current": 12,
     *         "previous": 9,
     *         "trend": 33.33
     *       }
     *     }
     *   }
     * }
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
