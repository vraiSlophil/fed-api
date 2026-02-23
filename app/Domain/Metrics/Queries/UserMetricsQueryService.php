<?php

namespace App\Domain\Metrics\Queries;

use App\Models\Auth\User;
use App\Models\Tasks\Task;
use App\Models\Themes\Theme;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\DB;

class UserMetricsQueryService
{
    /**
     * Build the metrics payload for the requested user and period.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  string  $period  Requested analytics period key.
     * @return array Structured metrics payload returned to the caller.
     */
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

    /**
     * Compute aggregate overview metrics for the user.
     *
     * @param  string  $userId  Identifier of the user.
     * @return array Structured metrics payload returned to the caller.
     */
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

    /**
     * Compute theme creation metrics over the requested date range.
     *
     * @param  string  $userId  Identifier of the user.
     * @param  CarbonImmutable  $startDate  Inclusive start date for the reporting window.
     * @param  CarbonImmutable  $endDate  Inclusive end date for the reporting window.
     * @return array Structured metrics payload returned to the caller.
     */
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

    /**
     * Compute task creation and completion metrics over the requested date range.
     *
     * @param  string  $userId  Identifier of the user.
     * @param  CarbonImmutable  $startDate  Inclusive start date for the reporting window.
     * @param  CarbonImmutable  $endDate  Inclusive end date for the reporting window.
     * @return array Structured metrics payload returned to the caller.
     */
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

    /**
     * Compute activity streak and active-day metrics for the date range.
     *
     * @param  string  $userId  Identifier of the user.
     * @param  CarbonImmutable  $startDate  Inclusive start date for the reporting window.
     * @param  CarbonImmutable  $endDate  Inclusive end date for the reporting window.
     * @return array Structured metrics payload returned to the caller.
     */
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

    /**
     * Compute week-over-week and month-over-month productivity trends.
     *
     * @param  string  $userId  Identifier of the user.
     * @return array Productivity trend metrics for week-over-week and month-over-month comparisons.
     */
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

    /**
     * Collect unique activity dates from theme and task events.
     *
     * @param  string  $userId  Identifier of the user.
     * @param  CarbonImmutable  $startDate  Inclusive start date for the reporting window.
     * @param  CarbonImmutable  $endDate  Inclusive end date for the reporting window.
     * @return Collection Collection of matching records.
     */
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

    /**
     * Calculate the current consecutive-day activity streak.
     *
     * @param  Collection  $activeDays  Collection of `Y-m-d` dates when user activity occurred.
     * @return int Number of consecutive active days ending today.
     */
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

    /**
     * Calculate the longest consecutive-day activity streak.
     *
     * @param  Collection  $activeDays  Collection of `Y-m-d` dates when user activity occurred.
     * @return int Highest number of consecutive active days observed in the last year.
     */
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

    /**
     * Calculate percentage trend between current and previous periods.
     *
     * @param  int  $current  Current period value used for trend comparison.
     * @param  int  $previous  Previous period value used for trend comparison.
     * @return float Relative variation expressed as a percentage.
     */
    private function calculateTrend(int $current, int $previous): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * Calculate the percentage of active days within the reporting period.
     *
     * @param  int  $activeDays  Count of unique active days in the reporting window.
     * @param  CarbonImmutable  $startDate  Inclusive start date for the reporting window.
     * @param  CarbonImmutable  $endDate  Inclusive end date for the reporting window.
     * @return float Percentage value expressed as a float.
     */
    private function calculateActivityPercentage(int $activeDays, CarbonImmutable $startDate, CarbonImmutable $endDate): float
    {
        $totalDays = $startDate->diffInDays($endDate) + 1;

        return $totalDays > 0 ? round(($activeDays / $totalDays) * 100, 2) : 0.0;
    }

    /**
     * Calculate average daily count across the reporting period.
     *
     * @param  int  $total  Total number of records used in the metric.
     * @param  CarbonImmutable  $startDate  Inclusive start date for the reporting window.
     * @param  CarbonImmutable  $endDate  Inclusive end date for the reporting window.
     * @return float Average number of events per day across the requested period.
     */
    private function calculateAveragePerDay(int $total, CarbonImmutable $startDate, CarbonImmutable $endDate): float
    {
        $totalDays = $startDate->diffInDays($endDate) + 1;

        return $totalDays > 0 ? round($total / $totalDays, 2) : 0.0;
    }

    /**
     * Resolve start and end dates for the requested analytics period.
     *
     * @param  string  $period  Requested analytics period key.
     * @return array Start and end dates computed from the requested period key.
     */
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

    /**
     * Fill missing dates in a time-series dataset with zero values.
     *
     * @param  Collection|Enumerable  $data  Query result indexed by date with aggregated counts.
     * @param  CarbonImmutable  $startDate  Inclusive start date for the reporting window.
     * @param  CarbonImmutable  $endDate  Inclusive end date for the reporting window.
     * @param  string  $dateField  Data field containing the date value in the source payload.
     * @param  string  $valueField  Data field containing the metric value in the source payload.
     * @return array Time-series data with missing dates filled using zero values.
     */
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
