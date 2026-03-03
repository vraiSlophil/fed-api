<?php

namespace App\Domain\Metrics\Queries;

use App\Models\Auth\User;
use App\Models\Tasks\Task;
use App\Models\Themes\Theme;

class StatsQueryService
{
    /**
     * Compute global task statistics visible to the authenticated user.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @return array Global statistics visible to the authenticated user.
     */
    public function globalForUser(User $user): array
    {
        return $this->buildTaskStats($user->user_id, null);
    }

    /**
     * Compute task statistics for a specific theme visible to the authenticated user.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @return array Theme-scoped statistics visible to the authenticated user.
     */
    public function forTheme(User $user, Theme $theme): array
    {
        $stats = $this->buildTaskStats($user->user_id, $theme->theme_id);
        $stats['theme'] = [
            'theme_id' => $theme->theme_id,
            'title' => $theme->title,
            'color' => $theme->color,
        ];

        return $stats;
    }

    /**
     * Build task counters and completion rate for the requested scope.
     *
     * @param  string  $userId  Identifier of the user.
     * @param  ?string  $themeId  Identifier of the theme.
     * @return array Structured metrics payload returned to the caller.
     */
    private function buildTaskStats(string $userId, ?string $themeId): array
    {
        $sevenDaysAgo = now()->subDays(7);

        $query = Task::query()->visibleToUser($userId);

        if ($themeId) {
            $query->where('theme_id', $themeId);
        }

        $stats = (clone $query)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN archived_at IS NULL THEN 1 ELSE 0 END) AS active')
            ->selectRaw('SUM(CASE WHEN archived_at IS NOT NULL THEN 1 ELSE 0 END) AS archived')
            ->selectRaw("SUM(CASE WHEN archived_at IS NULL AND status = 'todo' THEN 1 ELSE 0 END) AS todo")
            ->selectRaw("SUM(CASE WHEN archived_at IS NULL AND status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress")
            ->selectRaw("SUM(CASE WHEN archived_at IS NULL AND status = 'done' THEN 1 ELSE 0 END) AS done")
            ->selectRaw(
                'SUM(CASE WHEN archived_at IS NULL AND created_at >= ? THEN 1 ELSE 0 END) AS recently_created',
                [$sevenDaysAgo]
            )
            ->selectRaw(
                "SUM(CASE WHEN archived_at IS NULL AND status = 'done' AND validated_at IS NOT NULL AND validated_at >= ? THEN 1 ELSE 0 END) AS recently_completed",
                [$sevenDaysAgo]
            )
            ->first();

        $active = (int) ($stats?->active ?? 0);
        $done = (int) ($stats?->done ?? 0);

        return [
            'total' => (int) ($stats?->total ?? 0),
            'active' => $active,
            'archived' => (int) ($stats?->archived ?? 0),
            'todo' => (int) ($stats?->todo ?? 0),
            'in_progress' => (int) ($stats?->in_progress ?? 0),
            'done' => $done,
            'recently_created' => (int) ($stats?->recently_created ?? 0),
            'recently_completed' => (int) ($stats?->recently_completed ?? 0),
            'completion_rate' => $active > 0 ? round(($done / $active) * 100, 2) : 0,
        ];
    }
}
