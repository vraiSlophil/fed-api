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
    public function __construct(private readonly StatsQueryService $statsQueryService) {}

    public function globalStats(Request $request): JsonResponse
    {
        $stats = $this->statsQueryService->globalForUser($request->user());

        return ApiResponse::builder()
            ->success()
            ->messageCode('stats.global.success')
            ->data($stats)
            ->json();
    }

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
