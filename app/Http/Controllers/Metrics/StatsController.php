<?php

namespace App\Http\Controllers\Metrics;

use App\Domain\Metrics\Queries\StatsQueryService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Themes\Theme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Metrics
 *
 * Endpoints that expose aggregate task and activity metrics for users and themes.
 */
class StatsController extends Controller
{
    /**
     * Initialize the controller with task statistics query handlers.
     *
     * @param  StatsQueryService  $statsQueryService  Service that computes global and theme task statistics.
     */
    public function __construct(private readonly StatsQueryService $statsQueryService) {}

    /**
     * Return global task statistics for the authenticated user.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @response 200 {
     *   "status": "success",
     *   "message": "Ok",
     *   "message_code": "stats.global.success",
     *   "data": {
     *     "total": 32,
     *     "active": 24,
     *     "archived": 8,
     *     "todo": 10,
     *     "in_progress": 7,
     *     "done": 7,
     *     "recently_created": 3,
     *     "recently_completed": 2,
     *     "completion_rate": 29.17
     *   }
     * }
     *
     * @responseFile 401 resources/docs/responses/errors/auth-failed.json
     */
    public function globalStats(Request $request): JsonResponse
    {
        $stats = $this->statsQueryService->globalForUser($request->user());

        return ApiResponse::builder()
            ->success()
            ->messageCode('stats.global.success')
            ->data($stats)
            ->json();
    }

    /**
     * Return task statistics for a specific theme.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @responseFile 200 resources/docs/responses/success.json {"message_code":"stats.theme.success","data":{"total":12,"active":9,"archived":3,"todo":4,"in_progress":3,"done":2,"recently_created":1,"recently_completed":1,"completion_rate":16.67}}
     * @responseFile 403 resources/docs/responses/errors/forbidden.json
     */
    public function themeStats(Request $request, Theme $theme): JsonResponse
    {
        $this->authorize('view', $theme);

        $stats = $this->statsQueryService->forTheme($request->user(), $theme);

        return ApiResponse::builder()
            ->success()
            ->messageCode('stats.theme.success', ['theme' => $theme->theme_id])
            ->data($stats)
            ->json();
    }
}
