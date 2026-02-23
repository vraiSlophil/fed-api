<?php

namespace App\Domain\Tasks\Queries;

use App\Models\Auth\User;
use App\Models\Tasks\Task;
use App\Models\Themes\Theme;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class TaskQueryService
{
    /**
     * Load a theme by ID before creating a task inside it.
     *
     * @param  string  $themeId  Identifier of the theme.
     * @return Theme Theme instance returned after successful execution.
     */
    public function findThemeForCreation(string $themeId): Theme
    {
        return Theme::query()
            ->where('theme_id', $themeId)
            ->firstOrFail();
    }

    /**
     * Paginate tasks visible to the authenticated user.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  array  $filters  Filtering options applied to the query.
     * @param  array  $pagination  Pagination options such as page and per-page values.
     * @return LengthAwarePaginator Paginated collection of matching records.
     */
    public function paginateForUser(User $user, array $filters, array $pagination): LengthAwarePaginator
    {
        $query = $this->buildTasksQueryForUser($user, $filters);

        $query = $this->applyFiltersAndSorting($query, $filters);

        return $query->paginate($pagination['per_page'], ['*'], 'page', $pagination['page']);
    }

    /**
     * Find one task the authenticated user is allowed to view.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  string  $taskId  Identifier of the task.
     * @return Task Task instance returned after successful execution.
     */
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

    /**
     * Build the base task query restricted to user visibility rules.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  array  $filters  Filtering options applied to the query.
     * @return Builder Configured query builder instance.
     */
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

    /**
     * Apply filters and sorting options to the task query.
     *
     * @param  Builder  $query  Query builder instance used to compose the data access query.
     * @param  array  $filters  Filtering options applied to the query.
     * @return Builder Configured query builder instance.
     */
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
