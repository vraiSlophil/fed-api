<?php

namespace App\Domain\Tasks\Queries;

use App\Models\Auth\User;
use App\Models\Tasks\Task;
use App\Models\Themes\Theme;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class TaskQueryService
{
    public function paginateForUser(User $user, array $filters, array $pagination): LengthAwarePaginator
    {
        $query = $this->buildTasksQueryForUser($user, $filters);

        $query = $this->applyFiltersAndSorting($query, $filters);

        return $query->paginate($pagination['per_page'], ['*'], 'page', $pagination['page']);
    }

    public function findVisibleTaskForUser(User $user, string $taskId): Task
    {
        return Task::query()
            ->where('task_id', $taskId)
            ->where(function (Builder $query) use ($user): void {
                $query->where('user_id', $user->user_id)
                    ->orWhereHas('theme.themeUserPermissions', function (Builder $q) use ($user): void {
                        $q->where('user_id', $user->user_id)
                            ->where('can_view', true)
                            ->where('status', 'active');
                    });
            })
            ->firstOrFail();
    }

    private function buildTasksQueryForUser(User $user, array $filters): Builder
    {
        $query = Task::query()->where(function (Builder $query) use ($user): void {
            $query->where('user_id', $user->user_id)
                ->orWhereHas('theme.themeUserPermissions', function (Builder $q) use ($user): void {
                    $q->where('user_id', $user->user_id)
                        ->where('can_view', true)
                        ->where('status', 'active');
                });
        });

        $themeId = $filters['theme_id'] ?? null;
        if (is_string($themeId) && $themeId !== '') {
            $query->where('theme_id', $themeId);

            Theme::query()
                ->where('theme_id', $themeId)
                ->where(function (Builder $q) use ($user): void {
                    $q->where('owner_id', $user->user_id)
                        ->orWhereHas('themeUserPermissions', function (Builder $subq) use ($user): void {
                            $subq->where('user_id', $user->user_id)
                                ->where('can_view', true)
                                ->where('status', 'active');
                        });
                })
                ->firstOrFail();
        }

        return $query;
    }

    private function applyFiltersAndSorting(Builder $query, array $filters): Builder
    {
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (array_key_exists('archived', $filters)) {
            if (filter_var($filters['archived'], FILTER_VALIDATE_BOOLEAN)) {
                $query->whereNotNull('archived_at');
            } else {
                $query->whereNull('archived_at');
            }
        } else {
            $query->whereNull('archived_at');
        }

        if (array_key_exists('validated', $filters)) {
            if (filter_var($filters['validated'], FILTER_VALIDATE_BOOLEAN)) {
                $query->whereNotNull('validated_at');
            } else {
                $query->whereNull('validated_at');
            }
        }

        if (! empty($filters['statuses']) && is_string($filters['statuses'])) {
            $statuses = array_values(array_filter(array_map('trim', explode(',', $filters['statuses']))));
            if ($statuses !== []) {
                $query->whereIn('status', $statuses);
            }
        }

        if (! empty($filters['search']) && is_string($filters['search'])) {
            $search = mb_strtolower($filters['search'], 'UTF-8');
            $query->whereRaw('LOWER(title) LIKE ?', ['%'.$search.'%']);
        }

        $direction = ($filters['sort'] ?? null) === 'asc' ? 'asc' : 'desc';
        $query->orderBy('created_at', $direction);

        return $query;
    }
}
