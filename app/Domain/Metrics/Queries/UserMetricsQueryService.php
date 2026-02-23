<?php

namespace App\Domain\Metrics\Queries;

use App\Models\Auth\User;
use App\Models\Tasks\Task;
use App\Models\Themes\Theme;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserMetricsQueryService
{
    public function metricsFor(User $user, string $period): array
    {
        [$startDate, $endDate] = $this->getDateRange($period);

        return [
            'overview' => $this->getOverviewMetrics($user->user_id),
            'themes_over_time' => $this->getThemeMetrics($user->user_id, $startDate, $endDate),
            'tasks_over_time' => $this->getTaskMetrics($user->user_id, $startDate, $endDate),
            'activity_metrics' => $this->getActivityMetrics($user->user_id, $startDate, $endDate),
            'productivity_trends' => $this->getProductivityTrends($user->user_id),
        ];
    }

    private function getOverviewMetrics(string $userId): array
    {
        $totalThemes = Theme::where('owner_id', $userId)->count();
        $taskStats = Task::query()
            ->where('user_id', $userId)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) AS completed")
            ->first();

        $totalTasks = (int) ($taskStats?->total ?? 0);
        $completedTasks = (int) ($taskStats?->completed ?? 0);

        $memberOf = Theme::whereHas('themeUserPermissions', function ($q) use ($userId): void {
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

    private function getThemeMetrics(string $userId, CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $themes = Theme::where('owner_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'data' => $this->fillDateGaps($themes, $startDate, $endDate, 'date', 'count'),
            'total_in_period' => (int) $themes->sum('count'),
            'average_per_day' => $this->calculateAveragePerDay((int) $themes->sum('count'), $startDate, $endDate),
        ];
    }

    private function getTaskMetrics(string $userId, CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $tasksCreated = Task::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $tasksCompleted = Task::where('user_id', $userId)
            ->whereBetween('validated_at', [$startDate, $endDate])
            ->whereNotNull('validated_at')
            ->selectRaw('DATE(validated_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'created' => [
                'data' => $this->fillDateGaps($tasksCreated, $startDate, $endDate, 'date', 'count'),
                'total' => (int) $tasksCreated->sum('count'),
            ],
            'completed' => [
                'data' => $this->fillDateGaps($tasksCompleted, $startDate, $endDate, 'date', 'count'),
                'total' => (int) $tasksCompleted->sum('count'),
            ],
        ];
    }

    private function getActivityMetrics(string $userId, CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $activeDays = $this->getActiveDays($userId, $startDate, $endDate);

        return [
            'active_days_count' => $activeDays->count(),
            'active_days' => $activeDays->values()->all(),
            'current_streak' => $this->calculateCurrentStreak($activeDays),
            'longest_streak' => $this->calculateLongestStreak($activeDays),
            'activity_percentage' => $this->calculateActivityPercentage($activeDays->count(), $startDate, $endDate),
        ];
    }

    private function getProductivityTrends(string $userId): array
    {
        $now = CarbonImmutable::now();
        $thisWeekStart = $now->startOfWeek();
        $lastWeekStart = $now->subWeek()->startOfWeek();
        $lastWeekEnd = $now->subWeek()->endOfWeek();
        $thisMonthStart = $now->startOfMonth();
        $lastMonthStart = $now->subMonth()->startOfMonth();
        $lastMonthEnd = $now->subMonth()->endOfMonth();

        $stats = Task::query()
            ->where('user_id', $userId)
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS this_week', [$thisWeekStart])
            ->selectRaw('SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS last_week', [$lastWeekStart, $lastWeekEnd])
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS this_month', [$thisMonthStart])
            ->selectRaw('SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS last_month', [$lastMonthStart, $lastMonthEnd])
            ->first();

        $thisWeek = (int) ($stats?->this_week ?? 0);
        $lastWeek = (int) ($stats?->last_week ?? 0);
        $thisMonth = (int) ($stats?->this_month ?? 0);
        $lastMonth = (int) ($stats?->last_month ?? 0);

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

    private function getActiveDays(string $userId, CarbonImmutable $startDate, CarbonImmutable $endDate): Collection
    {
        $themeActiveDays = Theme::query()->where('owner_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) AS activity_date');

        $taskCreatedDays = Task::query()->where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) AS activity_date');

        $taskUpdatedDays = Task::query()->where('user_id', $userId)
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->selectRaw('DATE(updated_at) AS activity_date');

        $taskValidatedDays = Task::query()->where('user_id', $userId)
            ->whereNotNull('validated_at')
            ->whereBetween('validated_at', [$startDate, $endDate])
            ->selectRaw('DATE(validated_at) AS activity_date');

        $activityDays = DB::query()
            ->fromSub(
                $themeActiveDays
                    ->unionAll($taskCreatedDays)
                    ->unionAll($taskUpdatedDays)
                    ->unionAll($taskValidatedDays),
                'activity_days'
            )
            ->select('activity_date')
            ->distinct()
            ->orderBy('activity_date')
            ->pluck('activity_date')
            ->map(static fn ($date) => (string) $date);

        return collect($activityDays);
    }

    private function calculateCurrentStreak(Collection $activeDays): int
    {
        if ($activeDays->isEmpty()) {
            return 0;
        }

        $activeSet = array_fill_keys($activeDays->all(), true);
        $streak = 0;
        $cursor = Carbon::today();

        while ($cursor->greaterThan(Carbon::today()->subDays(365))) {
            if (! isset($activeSet[$cursor->toDateString()])) {
                break;
            }
            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }

    private function calculateLongestStreak(Collection $activeDays): int
    {
        if ($activeDays->isEmpty()) {
            return 0;
        }

        $activeSet = array_fill_keys($activeDays->all(), true);
        $longest = 0;
        $current = 0;

        $period = CarbonPeriod::create(Carbon::today()->subDays(365), Carbon::today());
        foreach ($period as $day) {
            if (isset($activeSet[$day->toDateString()])) {
                $current++;
                $longest = max($longest, $current);
            } else {
                $current = 0;
            }
        }

        return $longest;
    }

    private function calculateTrend(int $current, int $previous): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    private function calculateActivityPercentage(int $activeDays, CarbonImmutable $startDate, CarbonImmutable $endDate): float
    {
        $totalDays = $startDate->diffInDays($endDate) + 1;

        return $totalDays > 0 ? round(($activeDays / $totalDays) * 100, 2) : 0.0;
    }

    private function calculateAveragePerDay(int $total, CarbonImmutable $startDate, CarbonImmutable $endDate): float
    {
        $totalDays = $startDate->diffInDays($endDate) + 1;

        return $totalDays > 0 ? round($total / $totalDays, 2) : 0.0;
    }

    private function getDateRange(string $period): array
    {
        $end = CarbonImmutable::now();

        $start = match ($period) {
            '7_days' => $end->subDays(7),
            '30_days' => $end->subDays(30),
            '3_months' => $end->subMonths(3),
            '6_months' => $end->subMonths(6),
            '12_months' => $end->subMonths(12),
            'all_time' => CarbonImmutable::createFromTimestamp(0),
            default => $end->subMonths(12),
        };

        return [$start, $end];
    }

    private function fillDateGaps($data, CarbonImmutable $startDate, CarbonImmutable $endDate, string $dateField, string $valueField): array
    {
        $result = [];

        $current = $startDate;
        $dataByDate = $data->keyBy($dateField);

        while ($current->lessThanOrEqualTo($endDate)) {
            $dateKey = $current->format('Y-m-d');
            $result[] = [
                'date' => $dateKey,
                'value' => (int) ($dataByDate->get($dateKey)?->$valueField ?? 0),
            ];
            $current = $current->addDay();
        }

        return $result;
    }
}
