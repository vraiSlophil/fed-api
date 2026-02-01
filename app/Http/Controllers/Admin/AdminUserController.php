<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\Task;
use App\Models\Theme;
use App\Models\ThemeUserPermission;
use App\Models\User;
use Exception;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['role']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role_power', $request->role);
        }

        if ($request->filled('status')) {
            if ($request->status === 'blocked') {
                $query->whereNotNull('blocked_at');
            } elseif ($request->status === 'active') {
                $query->whereNotNull('email_verified_at');
                $query->whereNull('blocked_at');
            } elseif ($request->status === 'unverified') {
                $query->whereNull('email_verified_at');
                $query->whereNull('blocked_at');
            } else {
                throw new ApiException(
                    'validation.invalid',
                    ['field' => 'status', 'allowed' => ['blocked', 'active', 'unverified']],
                    422
                );
            }
        }

        if ($request->filled('roles')) {
            $roles = explode(',', $request->roles);
            $query->whereIn('role_power', $roles);
        }

        $sortField = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort', 'desc');

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

        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'created_at';
        }

        $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->input('per_page', 20);
        $perPage = max(1, min(100, intval($perPage)));

        $users = $query->paginate($perPage);

        return ApiResponse::builder()
            ->success()
            ->data([
                'users' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'last_page' => $users->lastPage(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ],
                'sorting' => [
                    'sort_by' => $sortField,
                    'sort_direction' => $sortDirection,
                    'available_sort_fields' => $allowedSortFields,
                ],
                'filters' => [
                    'search' => $request->input('search'),
                    'role' => $request->input('role'),
                    'status' => $request->input('status'),
                    'verified' => $request->input('verified'),
                    'roles' => $request->input('roles'),
                ],
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
            ])
            ->json();
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string|max:50|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'role_power' => 'required|exists:roles,power',
            'avatar' => 'nullable|image|max:2048',
        ]);

        $data = $request->only([
            'username',
            'email',
            'first_name',
            'last_name',
            'role_power'
        ]);
        $data['password'] = Hash::make($request->password);

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user = User::create($data);

        event(new Registered($user));

        return ApiResponse::builder()
            ->success(201)
            ->messageCode('user.create.success')
            ->data($user)
            ->json();
    }

    public function show(User $user): JsonResponse
    {
        $user->load([
            'role',
            'themes',
        ]);

        $lastActivity = null;
        if ($user->last_login_at) {
            $lastActivity = $user->last_login_at;
        }
        if ($user->updated_at && (!$lastActivity || $user->updated_at > $lastActivity)) {
            $lastActivity = $user->updated_at->toDateTimeString();
        }

        $totalTasks = $user->tasks()->count();
        $completedTasks = $user->tasks()->where('status', 'done')->count();
        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;

        $recentTasksCount = $user->tasks()->where('created_at', '>=', now()->subDays(7))->count();
        $recentThemesCount = $user->themes()->where('created_at', '>=', now()->subDays(7))->count();

        $activeDaysLast30 = $this->getActiveDaysCount($user->user_id, 30);

        $additionalStats = [
            'themes_count' => $user->themes()->count(),
            'tasks_count' => $totalTasks,
            'completed_tasks_count' => $completedTasks,
            'completion_rate_percentage' => $completionRate,

            'last_activity' => $lastActivity,
            'account_age_days' => $user->created_at->diffInDays(now(), false), // Entier
            'account_age_human' => $user->created_at->diffForHumans(), // Format lisible
            'days_since_last_login' => $user->last_login_at ?
                now()->diffInDays($user->last_login_at) : null,

            'themes_as_member' => ThemeUserPermission::where('user_id', $user->user_id)
                ->where('status', 'active')->count(),
            'pending_invitations' => Invitation::where('invitee_user_id', $user->user_id)
                ->where('status', 'pending')->count(),

            'recent_activity' => [
                'tasks_last_7_days' => $recentTasksCount,
                'themes_last_7_days' => $recentThemesCount,
                'active_days_last_30' => $activeDaysLast30,
            ],

            'average_tasks_per_theme' => $user->themes()->count() > 0 ?
                round($totalTasks / $user->themes()->count(), 1) : 0,
            'archived_tasks_count' => $user->tasks()->whereNotNull('archived_at')->count(),
            'validated_tasks_count' => $user->tasks()->whereNotNull('validated_at')->count(),

            'is_blocked' => $user->blocked_at !== null,
            'is_email_verified' => $user->email_verified_at !== null,
            'blocked_since' => $user->blocked_at?->diffForHumans(),
            'verified_since' => $user->email_verified_at?->diffForHumans(),
        ];

        return ApiResponse::builder()
            ->success()
            ->messageCode('user.show.success')
            ->data([
                'user' => $user,
                'additional_stats' => $additionalStats,
            ])
            ->json();
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'username' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($user->user_id, 'user_id')],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->user_id, 'user_id')],
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'role_power' => 'required|exists:roles,power',
            'password' => 'nullable|string|min:8|confirmed',
            'avatar' => 'nullable|image|max:2048',
        ]);

        $data = $request->only([
            'username',
            'email',
            'first_name',
            'last_name',
            'role_power',
        ]);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        $emailChanged = $user->email !== $data['email'];

        $user->update($data);

        if ($emailChanged) {
            $user->email_verified_at = null;
            $user->save();
            $user->sendEmailVerificationNotification();

            return ApiResponse::builder()
                ->success()
                ->messageCode('user.update.email')
                ->data($user)
                ->json();
        }

        return ApiResponse::builder()
            ->success()
            ->messageCode('user.update.success')
            ->data($user)
            ->json();
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->user_id === auth()->user()->user_id) {
            throw new ApiException('user.delete.forbidden_self', [], 400);
        }

        try {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $user->forceDelete();

            return ApiResponse::builder()
                ->success()
                ->messageCode('user.delete.success')
                ->json();

        } catch (Exception $e) {
            if ($e instanceof QueryException && $e->getCode() === '23000') {
                throw new ApiException('user.delete.failed_foreign_key', [], 409);
            }

            throw new ApiException('user.delete.failed', [], 500);
        }
    }

    public function block(User $user): JsonResponse
    {
        if ($user->blocked_at !== null) {
            throw new ApiException('user.block.already_blocked', [], 400);
        }

        if ($user->user_id === auth()->user()->user_id) {
            throw new ApiException('user.block.forbidden_self', [], 400);
        }

        $user->update(['blocked_at' => now()]);

        return ApiResponse::builder()
            ->success(200)
            ->messageCode('user.block.success')
            ->data($user)
            ->json();
    }

    public function unblock(User $user): JsonResponse
    {
        if ($user->blocked_at === null) {
            throw new ApiException('user.unblock.not_blocked', [], 400);
        }

        $user->update(['blocked_at' => null]);

        return ApiResponse::builder()
            ->success(200)
            ->messageCode('user.unblock.success')
            ->data($user)
            ->json();
    }

    private function getActiveDaysCount(string $userId, int $days): int
    {
        $startDate = now()->subDays($days);
        $endDate = now();

        $themeDays = Theme::where('owner_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date')
            ->distinct()
            ->pluck('date');

        $taskDays = Task::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date')
            ->distinct()
            ->pluck('date');

        return $themeDays->concat($taskDays)->unique()->count();
    }
}
