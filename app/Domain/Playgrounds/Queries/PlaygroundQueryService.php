<?php

namespace App\Domain\Playgrounds\Queries;

use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;
use App\Models\Tasks\Task;
use App\Models\Themes\Theme;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PlaygroundQueryService
{
    public function listForUser(User $user): Collection
    {
        return $user->playgrounds()
            ->withCount(['themes'])
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function findForUserById(User $user, string $playgroundId, bool $withThemesCount = false): Playground
    {
        $query = Playground::query()
            ->where('playground_id', $playgroundId)
            ->where('user_id', $user->user_id);

        if ($withThemesCount) {
            $query->withCount(['themes']);
        }

        return $query->firstOrFail();
    }

    public function findForUserBySlug(User $user, string $slug, bool $withThemesCount = false): Playground
    {
        $query = Playground::query()
            ->where('slug', $slug)
            ->where('user_id', $user->user_id);

        if ($withThemesCount) {
            $query->withCount(['themes']);
        }

        return $query->firstOrFail();
    }

    public function paginateAccessibleThemes(User $user, Playground $playground, array $pagination): LengthAwarePaginator
    {
        $themesQuery = $this->buildAccessibleThemesQuery($playground->playground_id, $user->user_id);

        return $themesQuery->paginate($pagination['per_page'], ['*'], 'page', $pagination['page']);
    }

    public function statsFor(Playground $playground): array
    {
        $taskCounts = Task::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) as todo")
            ->selectRaw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress")
            ->selectRaw("SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done")
            ->whereHas('theme', fn ($query) => $query->where('playground_id', $playground->playground_id))
            ->first();

        $themes = $playground->themes();

        return [
            'themes' => [
                'total' => (int) $themes->count(),
                'private' => (int) (clone $themes)->where('visibility', 'private')->count(),
                'shared' => (int) (clone $themes)->where('visibility', 'shared')->count(),
                'public' => (int) (clone $themes)->where('visibility', 'public')->count(),
            ],
            'tasks' => [
                'total' => (int) ($taskCounts?->total ?? 0),
                'todo' => (int) ($taskCounts?->todo ?? 0),
                'in_progress' => (int) ($taskCounts?->in_progress ?? 0),
                'done' => (int) ($taskCounts?->done ?? 0),
            ],
            'completion_rate' => $this->calculateCompletionRate($playground),
            'recent_activity' => $this->getRecentActivity($playground),
        ];
    }

    private function calculateCompletionRate(Playground $playground): float
    {
        $totals = Task::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed")
            ->whereHas('theme', fn ($query) => $query->where('playground_id', $playground->playground_id))
            ->first();

        $total = (int) ($totals?->total ?? 0);
        $completed = (int) ($totals?->completed ?? 0);

        if ($total === 0) {
            return 0.0;
        }

        return (float) number_format(($completed / $total) * 100.0, 2, '.', '');
    }

    private function getRecentActivity(Playground $playground): array
    {
        $recentTasks = Task::query()
            ->whereHas('theme', fn ($query) => $query->where('playground_id', $playground->playground_id))
            ->with(['theme:theme_id,title', 'user:user_id,username'])
            ->latest('updated_at')
            ->take(10)
            ->get();

        $recentThemes = $playground->themes()
            ->with(['owner:user_id,username'])
            ->latest('updated_at')
            ->take(5)
            ->get();

        return [
            'recent_tasks' => $recentTasks,
            'recent_themes' => $recentThemes,
        ];
    }

    private function buildAccessibleThemesQuery(string $playgroundId, string $userId): Builder
    {
        return Theme::query()
            ->where('playground_id', $playgroundId)
            ->where(function (Builder $query) use ($userId, $playgroundId): void {
                $query->where('owner_id', $userId)
                    ->orWhere(function (Builder $sharedQuery) use ($userId, $playgroundId): void {
                        $sharedQuery->where('owner_id', '!=', $userId)
                            ->whereHas('themeUserPermissions', function (Builder $permissionsQuery) use ($userId, $playgroundId): void {
                                $permissionsQuery->where('user_id', $userId)
                                    ->where('status', 'active')
                                    ->where('can_view', true)
                                    ->where('target_playground_id', $playgroundId);
                            });
                    });
            })
            ->orderByDesc('created_at');
    }
}
