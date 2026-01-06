<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Task;
use App\Models\Theme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function globalStats(Request $request): JsonResponse
    {
        $userId = $request->user()->user_id;
        $stats = $this->getTaskStats($userId);

        return ApiResponse::builder()
            ->success()
            ->messageCode('stats.global.success')
            ->data($stats)
            ->json();
    }

    public function themeStats(Request $request, string $themeId): JsonResponse
    {
        $userId = $request->user()->user_id;

        $theme = Theme::where('theme_id', $themeId)
            ->where(function ($query) use ($userId) {
                $query->where('owner_id', $userId)
                    ->orWhereHas('themeUserPermissions', function ($q) use ($userId) {
                        $q->where('user_id', $userId)
                            ->where('can_view', true)
                            ->where('status', 'active');
                    });
            })
            ->firstOrFail();

        $stats = $this->getTaskStats($userId, $themeId);
        $stats['theme'] = [
            'theme_id' => $theme->theme_id,
            'title' => $theme->title,
            'color' => $theme->color
        ];

        return ApiResponse::builder()
            ->success()
            ->messageCode('stats.theme.success', ['theme' => $theme->theme_id])
            ->data($stats)
            ->json();
    }

    private function getTaskStats(string $userId, ?string $themeId = null): array
    {
        $query = Task::where(function ($query) use ($userId) {
            $query->where('user_id', $userId);

            $query->orWhereHas('theme.themeUserPermissions', function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->where('can_view', true)
                    ->where('status', 'active');
            });
        });

        if ($themeId) {
            $query->where('theme_id', $themeId);
        }

        $totalTasks = (clone $query)->count();

        $activeTasks = (clone $query)
            ->whereNull('archived_at')
            ->count();

        $archivedTasks = (clone $query)
            ->whereNotNull('archived_at')
            ->count();

        $todoTasks = (clone $query)
            ->whereNull('archived_at')
            ->where('status', 'todo')
            ->count();

        $doingTasks = (clone $query)
            ->whereNull('archived_at')
            ->where('status', 'doing')
            ->count();

        $doneTasks = (clone $query)
            ->whereNull('archived_at')
            ->where('status', 'done')
            ->count();

        $recentlyCreatedTasks = (clone $query)
            ->whereNull('archived_at')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $recentlyCompletedTasks = (clone $query)
            ->whereNull('archived_at')
            ->where('status', 'done')
            ->whereNotNull('validated_at')
            ->where('validated_at', '>=', now()->subDays(7))
            ->count();

        $completionRate = $activeTasks > 0 ? round(($doneTasks / $activeTasks) * 100, 2) : 0;

        return [
            'total' => $totalTasks,
            'active' => $activeTasks,
            'archived' => $archivedTasks,
            'todo' => $todoTasks,
            'doing' => $doingTasks,
            'done' => $doneTasks,
            'recently_created' => $recentlyCreatedTasks,
            'recently_completed' => $recentlyCompletedTasks,
            'completion_rate' => $completionRate
        ];
    }
}
