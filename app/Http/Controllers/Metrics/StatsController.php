<?php

namespace App\Http\Controllers\Metrics;

use App\Domain\Metrics\Queries\StatsQueryService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Themes\Theme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
