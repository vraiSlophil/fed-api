<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Role;
use App\Models\User;
use App\Models\ThemeUserPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    /**
     * Liste des utilisateurs avec filtres et pagination (GET /users)
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with([
            'role']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username',
                    'like',
                    "%{$search}%")->orWhere('email',
                    'like',
                    "%{$search}%")->orWhere('first_name',
                    'like',
                    "%{$search}%")->orWhere('last_name',
                    'like',
                    "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role_power',
                $request->role);
        }

        if ($request->filled('status')) {
            if ($request->status === 'blocked') {
                $query->whereNotNull('blocked_at');
            } else {
                $query->whereNull('blocked_at');
            }
        }

        $users = $query->paginate(20);

        // Structure simplifiée
        return ApiResponse::success([
            'users' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
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
        ]);
    }

    /**
     * Créer un nouvel utilisateur (POST /users)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string|max:50|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'role_power' => 'required|exists:roles,power',
            'avatar' => 'nullable|image|max:2048',]);

        $data = $request->only([
            'username',
            'email',
            'first_name',
            'last_name',
            'role_power']);
        $data['password'] = Hash::make($request->password);

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')->store('avatars');
        }

        $user = User::create($data);

        return ApiResponse::success($user,
            'Utilisateur créé avec succès.');
    }

    /**
     * Affichage d'un utilisateur spécifique (GET /users/{user})
     */
    public function show(User $user): JsonResponse
    {
        $user->load([
            'role',
            'themes']);

        // Ajouter des statistiques supplémentaires pour l'admin
        $lastActivity = null;
        if ($user->last_login_at) {
            $lastActivity = $user->last_login_at;
        }
        if ($user->updated_at && (!$lastActivity || $user->updated_at > $lastActivity)) {
            $lastActivity = $user->updated_at->toDateTimeString();
        }

        // Calculer le taux de completion des tâches
        $totalTasks = $user->tasks()->count();
        $completedTasks = $user->tasks()->where('status', 'done')->count();
        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;

        // Activité récente (7 derniers jours)
        $recentTasksCount = $user->tasks()->where('created_at', '>=', now()->subDays(7))->count();
        $recentThemesCount = $user->themes()->where('created_at', '>=', now()->subDays(7))->count();

        // Nombre de jours d'activité dans les 30 derniers jours
        $activeDaysLast30 = $this->getActiveDaysCount($user->user_id, 30);

        $additionalStats = [
            // Stats de base (améliorées)
            'themes_count' => $user->themes()->count(),
            'tasks_count' => $totalTasks,
            'completed_tasks_count' => $completedTasks,
            'completion_rate_percentage' => $completionRate,

            // Temps et activité
            'last_activity' => $lastActivity,
            'account_age_days' => $user->created_at->diffInDays(now(), false), // Entier
            'account_age_human' => $user->created_at->diffForHumans(), // Format lisible
            'days_since_last_login' => $user->last_login_at ?
                now()->diffInDays($user->last_login_at) : null,

            // Collaboration
            'themes_as_member' => ThemeUserPermission::where('user_id', $user->user_id)
                ->where('status', 'active')->count(),
            'pending_invitations' => ThemeUserPermission::where('user_id', $user->user_id)
                ->where('status', 'invited')->count(),

            // Activité récente
            'recent_activity' => [
                'tasks_last_7_days' => $recentTasksCount,
                'themes_last_7_days' => $recentThemesCount,
                'active_days_last_30' => $activeDaysLast30,
            ],

            // Stats avancées
            'average_tasks_per_theme' => $user->themes()->count() > 0 ?
                round($totalTasks / $user->themes()->count(), 1) : 0,
            'archived_tasks_count' => $user->tasks()->whereNotNull('archived_at')->count(),
            'validated_tasks_count' => $user->tasks()->whereNotNull('validated_at')->count(),

            // Statut du compte
            'is_blocked' => $user->blocked_at !== null,
            'is_email_verified' => $user->email_verified_at !== null,
            'blocked_since' => $user->blocked_at?->diffForHumans(),
            'verified_since' => $user->email_verified_at?->diffForHumans(),
        ];

        return ApiResponse::success([
            'user' => $user,
            'additional_stats' => $additionalStats,
        ]);
    }

    /**
     * Mise à jour d'un utilisateur (PUT /users/{user})
     */
    public function update(Request $request,
                           User    $user): JsonResponse
    {
        $request->validate([
            'username' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users')->ignore($user->user_id,
                    'user_id')],
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore($user->user_id,
                    'user_id')],
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'role_power' => 'required|exists:roles,power',
            'password' => 'nullable|string|min:8|confirmed',
            'avatar' => 'nullable|image|max:2048',]);

        $data = $request->only([
            'username',
            'email',
            'first_name',
            'last_name',
            'role_power']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::delete($user->avatar_path);
            }
            $data['avatar_path'] = $request->file('avatar')->store('avatars');
        }

        $user->update($data);

        return ApiResponse::success($user,
            'Utilisateur mis à jour avec succès.');
    }

    /**
     * Supprimer un utilisateur (DELETE /users/{user})
     */
    public function destroy(User $user): JsonResponse
    {
        // Empêcher la suppression de son propre compte
        if ($user->user_id === auth()->user()->user_id) {
            return ApiResponse::error('Vous ne pouvez pas supprimer votre propre compte.',
                400);
        }

        if ($user->avatar_path) {
            Storage::delete($user->avatar_path);
        }

        $user->delete();

        return ApiResponse::success(null,
            'Utilisateur supprimé avec succès.');
    }

    /**
     * Bloquer un utilisateur (POST /users/{user}/block)
     */
    public function block(User $user): JsonResponse
    {
        if ($user->blocked_at !== null) {
            return ApiResponse::error('Cet utilisateur est déjà bloqué.',
                400);
        }

        // Empêcher de se bloquer soi-même
        if ($user->user_id === auth()->user()->user_id) {
            return ApiResponse::error('Vous ne pouvez pas bloquer votre propre compte.',
                400);
        }

        $user->update(['blocked_at' => now()]);
        return ApiResponse::success($user,
            'Utilisateur bloqué avec succès.');
    }

    /**
     * Débloquer un utilisateur (POST /users/{user}/unblock)
     */
    public function unblock(User $user): JsonResponse
    {
        if ($user->blocked_at === null) {
            return ApiResponse::error('Cet utilisateur n\'est pas bloqué.',
                400);
        }

        $user->update(['blocked_at' => null]);
        return ApiResponse::success($user,
            'Utilisateur débloqué avec succès.');
    }

    /**
     * Compte le nombre de jours d'activité dans une période donnée
     */
    private function getActiveDaysCount(string $userId, int $days): int
    {
        $startDate = now()->subDays($days);
        $endDate = now();

        // Jours où l'utilisateur a créé des thèmes
        $themeDays = \App\Models\Theme::where('owner_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date')
            ->distinct()
            ->pluck('date');

        // Jours où l'utilisateur a créé des tâches
        $taskDays = \App\Models\Task::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date')
            ->distinct()
            ->pluck('date');

        // Jours où l'utilisateur s'est connecté (si on stockait cette info)
        // Pour l'instant on se base uniquement sur la création de contenu

        return $themeDays->concat($taskDays)->unique()->count();
    }
}
