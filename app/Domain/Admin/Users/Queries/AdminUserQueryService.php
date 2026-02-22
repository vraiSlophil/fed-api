<?php

namespace App\Domain\Admin\Users\Queries;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Invitations\Invitation;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminUserQueryService
{
    /**
     * @return array{users:LengthAwarePaginator,sort_by:string,sort_direction:string,allowed_sort_fields:array<int,string>,filters:array<string,mixed>}
     */
    public function paginate(array $validated, array $pagination): array
    {
        $query = User::with(['role']);

        if (! empty($validated['search'])) {
            $search = (string) $validated['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['role'])) {
            $query->where('role_power', $validated['role']);
        }

        if (! empty($validated['status'])) {
            if ($validated['status'] === 'blocked') {
                $query->whereNotNull('blocked_at');
            }

            if ($validated['status'] === 'active') {
                $query->whereNotNull('email_verified_at')->whereNull('blocked_at');
            }

            if ($validated['status'] === 'unverified') {
                $query->whereNull('email_verified_at')->whereNull('blocked_at');
            }
        }

        if (! empty($validated['roles']) && is_string($validated['roles'])) {
            $roles = array_values(array_filter(array_map('trim', explode(',', $validated['roles']))));
            if ($roles !== []) {
                $query->whereIn('role_power', $roles);
            }
        }

        $allowedSortFields = [
            'created_at',
            'updated_at',
            'username',
            'email',
            'first_name',
            'last_name',
            'last_login_at',
            'email_verified_at',
            'blocked_at',
        ];

        $sortField = in_array($validated['sort_by'] ?? 'created_at', $allowedSortFields, true)
            ? (string) ($validated['sort_by'] ?? 'created_at')
            : 'created_at';

        $sortDirection = strtolower((string) ($validated['sort'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortField, $sortDirection);

        $users = $query->paginate($pagination['per_page'], ['*'], 'page', $pagination['page']);

        return [
            'users' => $users,
            'sort_by' => $sortField,
            'sort_direction' => $sortDirection,
            'allowed_sort_fields' => $allowedSortFields,
            'filters' => [
                'search' => $validated['search'] ?? null,
                'role' => $validated['role'] ?? null,
                'status' => $validated['status'] ?? null,
                'roles' => $validated['roles'] ?? null,
            ],
        ];
    }

    public function additionalStats(): array
    {
        return [
            'roles' => Role::all(['power', 'name']),
            'stats' => [
                'total_users' => User::count(),
                'active_users' => User::whereNull('blocked_at')->count(),
                'blocked_users' => User::whereNotNull('blocked_at')->count(),
                'verified_users' => User::whereNotNull('email_verified_at')->count(),
                'unverified_users' => User::whereNull('email_verified_at')->count(),
                'created_last_7_days' => User::where('created_at', '>=', now()->subDays(7))->count(),
                'verified_last_7_days' => User::where('email_verified_at', '>=', now()->subDays(7))->count(),
                'blocked_last_7_days' => User::where('blocked_at', '>=', now()->subDays(7))->count(),
            ],
        ];
    }

    public function details(User $user): array
    {
        $user->load(['role', 'themes']);

        $lastActivity = $user->last_login_at;
        if ($user->updated_at && (! $lastActivity || $user->updated_at > $lastActivity)) {
            $lastActivity = $user->updated_at;
        }

        $totalTasks = $user->tasks()->count();
        $completedTasks = $user->tasks()->where('status', 'done')->count();

        $themesCount = $user->themes()->count();

        return [
            'user' => $user,
            'additional_stats' => [
                'themes_count' => $themesCount,
                'tasks_count' => $totalTasks,
                'completed_tasks_count' => $completedTasks,
                'completion_rate_percentage' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0,
                'last_activity' => $lastActivity,
                'account_age_days' => $user->created_at->diffInDays(now(), false),
                'account_age_human' => $user->created_at->diffForHumans(),
                'days_since_last_login' => $user->last_login_at ? now()->diffInDays($user->last_login_at) : null,
                'themes_as_member' => $user->themeUserPermissions()->where('status', 'active')->count(),
                'pending_invitations' => Invitation::query()
                    ->where('invitee_user_id', $user->user_id)
                    ->where('status', 'pending')
                    ->count(),
                'recent_activity' => [
                    'tasks_last_7_days' => $user->tasks()->where('created_at', '>=', now()->subDays(7))->count(),
                    'themes_last_7_days' => $user->themes()->where('created_at', '>=', now()->subDays(7))->count(),
                    'active_days_last_30' => $this->getActiveDaysCount($user->user_id, 30),
                ],
                'average_tasks_per_theme' => $themesCount > 0 ? round($totalTasks / $themesCount, 1) : 0,
                'archived_tasks_count' => $user->tasks()->whereNotNull('archived_at')->count(),
                'validated_tasks_count' => $user->tasks()->whereNotNull('validated_at')->count(),
                'is_blocked' => $user->blocked_at !== null,
                'is_email_verified' => $user->email_verified_at !== null,
                'blocked_since' => $user->blocked_at?->diffForHumans(),
                'verified_since' => $user->email_verified_at?->diffForHumans(),
            ],
        ];
    }

    private function getActiveDaysCount(string $userId, int $days): int
    {
        $startDate = now()->subDays($days);
        $endDate = now();

        $themeDays = \App\Models\Themes\Theme::where('owner_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date')
            ->distinct()
            ->pluck('date');

        $taskDays = \App\Models\Tasks\Task::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date')
            ->distinct()
            ->pluck('date');

        return $themeDays->concat($taskDays)->unique()->count();
    }
}
