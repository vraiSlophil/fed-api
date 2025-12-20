<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Task;
use App\Models\Theme;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserMetricsController extends Controller
{
    public function getUserMetrics(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->user_id;
        $period = $request->input('period', '12_months');
        $dateRange = $this->getDateRange($period);

        $metrics = [
            'overview' => $this->getOverviewMetrics($userId),
            'themes_over_time' => $this->getThemeMetrics($userId, $dateRange),
            'tasks_over_time' => $this->getTaskMetrics($userId, $dateRange),
            'activity_metrics' => $this->getActivityMetrics($userId, $dateRange),
            'productivity_trends' => $this->getProductivityTrends($userId),
        ];

        return ApiResponse::builder()
            ->success()
            ->messageCode('user.metrics.retrieved', ['period' => $period])
            ->data($metrics)
            ->json();
    }

    private function getOverviewMetrics(string $userId): array
    {
        $totalThemes = Theme::where('owner_id', $userId)->count();
        $totalTasks = Task::where('user_id', $userId)->count();
        $completedTasks = Task::where('user_id', $userId)
            ->where('status', 'done')
            ->count();

        $memberOf = Theme::whereHas('themeUserPermissions', function ($q) use ($userId) {
            $q->where('user_id', $userId)->where('status', 'active');
        })->count();

        return [
            'total_themes_owned' => $totalThemes,
            'total_themes_member' => $memberOf,
            'total_tasks_created' => $totalTasks,
            'total_tasks_completed' => $completedTasks,
            'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0,
        ];
    }

    private function getThemeMetrics(string $userId, array $dateRange): array
    {
        $themes = Theme::where('owner_id', $userId)
            ->whereBetween('created_at', $dateRange)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'data' => $this->fillDateGaps($themes, $dateRange, 'date', 'count'),
            'total_in_period' => $themes->sum('count'),
            'average_per_day' => $this->calculateAveragePerDay($themes->sum('count'), $dateRange),
        ];
    }

    private function getTaskMetrics(string $userId, array $dateRange): array
    {
        $tasksCreated = Task::where('user_id', $userId)
            ->whereBetween('created_at', $dateRange)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $tasksCompleted = Task::where('user_id', $userId)
            ->whereBetween('validated_at', $dateRange)
            ->whereNotNull('validated_at')
            ->selectRaw('DATE(validated_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'created' => [
                'data' => $this->fillDateGaps($tasksCreated, $dateRange, 'date', 'count'),
                'total' => $tasksCreated->sum('count'),
            ],
            'completed' => [
                'data' => $this->fillDateGaps($tasksCompleted, $dateRange, 'date', 'count'),
                'total' => $tasksCompleted->sum('count'),
            ],
        ];
    }

    private function getActivityMetrics(string $userId, array $dateRange): array
    {
        $activeDays = $this->getActiveDays($userId, $dateRange);

        return [
            'active_days_count' => $activeDays->count(),
            'active_days' => $activeDays->toArray(),
            'current_streak' => $this->calculateCurrentStreak($userId),
            'longest_streak' => $this->calculateLongestStreak($userId),
            'activity_percentage' => $this->calculateActivityPercentage($activeDays->count(), $dateRange),
        ];
    }

    private function getProductivityTrends(string $userId): array
    {
        $thisWeek = Task::where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();

        $lastWeek = Task::where('user_id', $userId)
            ->whereBetween('created_at', [
                now()->subWeek()->startOfWeek(),
                now()->subWeek()->endOfWeek()
            ])
            ->count();

        $thisMonth = Task::where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        $lastMonth = Task::where('user_id', $userId)
            ->whereBetween('created_at', [
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth()
            ])
            ->count();

        return [
            'weekly' => [
                'current' => $thisWeek,
                'previous' => $lastWeek,
                'trend' => $this->calculateTrend($thisWeek, $lastWeek),
            ],
            'monthly' => [
                'current' => $thisMonth,
                'previous' => $lastMonth,
                'trend' => $this->calculateTrend($thisMonth, $lastMonth),
            ],
        ];
    }

    private function calculateTrend(int $current, int $previous): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 2);
    }

    private function getActiveDays(string $userId, array $dateRange): \Illuminate\Support\Collection
    {
        $themeActiveDays = Theme::where('owner_id', $userId)
            ->whereBetween('created_at', $dateRange)
            ->selectRaw('DATE(created_at) as date')
            ->distinct()
            ->pluck('date');

        $taskActiveDays = Task::where('user_id', $userId)
            ->whereBetween('created_at', $dateRange)
            ->selectRaw('DATE(created_at) as date')
            ->distinct()
            ->pluck('date');

        return $themeActiveDays->concat($taskActiveDays)->unique()->sort()->values();
    }

    private function calculateCurrentStreak(string $userId): int
    {
        $streak = 0;
        $currentDate = Carbon::today();

        while ($currentDate->greaterThan(Carbon::today()->subDays(365))) {
            $hasActivity = $this->hasActivityOnDate($userId, $currentDate);

            if ($hasActivity) {
                $streak++;
                $currentDate->subDay();
            } else {
                break;
            }
        }

        return $streak;
    }

    private function calculateLongestStreak(string $userId): int
    {
        $longestStreak = 0;
        $currentStreak = 0;
        $startDate = Carbon::today()->subDays(365);
        $endDate = Carbon::today();

        while ($startDate->lessThanOrEqualTo($endDate)) {
            if ($this->hasActivityOnDate($userId, $startDate)) {
                $currentStreak++;
                $longestStreak = max($longestStreak, $currentStreak);
            } else {
                $currentStreak = 0;
            }
            $startDate->addDay();
        }

        return $longestStreak;
    }

    private function hasActivityOnDate(string $userId, Carbon $date): bool
    {
        $dateString = $date->format('Y-m-d');

        $hasThemeActivity = Theme::where('owner_id', $userId)
            ->whereDate('created_at', $dateString)
            ->exists();

        $hasTaskActivity = Task::where('user_id', $userId)
            ->where(function ($q) use ($dateString) {
                $q->whereDate('created_at', $dateString)
                    ->orWhereDate('updated_at', $dateString);
            })
            ->exists();

        return $hasThemeActivity || $hasTaskActivity;
    }

    private function calculateActivityPercentage(int $activeDays, array $dateRange): float
    {
        [$startDate, $endDate] = $dateRange;
        $totalDays = $startDate->diffInDays($endDate) + 1;
        return $totalDays > 0 ? round(($activeDays / $totalDays) * 100, 2) : 0;
    }

    private function calculateAveragePerDay(int $total, array $dateRange): float
    {
        [$startDate, $endDate] = $dateRange;
        $totalDays = $startDate->diffInDays($endDate) + 1;
        return $totalDays > 0 ? round($total / $totalDays, 2) : 0;
    }

    private function getDateRange(string $period): array
    {
        $end = Carbon::now();

        $start = match ($period) {
            '7_days' => Carbon::now()->subDays(7),
            '30_days' => Carbon::now()->subDays(30),
            '3_months' => Carbon::now()->subMonths(3),
            '6_months' => Carbon::now()->subMonths(6),
            '12_months' => Carbon::now()->subMonths(12),
            'all_time' => Carbon::createFromTimestamp(0),
            default => Carbon::now()->subMonths(12),
        };

        return [$start, $end];
    }

    private function fillDateGaps($data, array $dateRange, string $dateField, string $valueField): array
    {
        [$startDate, $endDate] = $dateRange;
        $result = [];

        $current = $startDate->copy();
        $dataByDate = $data->keyBy($dateField);

        while ($current->lessThanOrEqualTo($endDate)) {
            $dateKey = $current->format('Y-m-d');
            $result[] = [
                'date' => $dateKey,
                'value' => $dataByDate->get($dateKey)?->$valueField ?? 0,
            ];
            $current->addDay();
        }

        return $result;
    }
}
