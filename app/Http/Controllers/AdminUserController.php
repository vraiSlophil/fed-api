<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Role;
use App\Models\User;
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
        $query = User::with(['role']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role_power', $request->role);
        }

        if ($request->filled('status')) {
            if ($request->status === 'blocked') {
                $query->whereNotNull('blocked_at');
            } else {
                $query->whereNull('blocked_at');
            }
        }

        $users = $query->paginate(20);
        $roles = Role::all();

        // Ajouter des statistiques globales
        $globalStats = ['total_users' => User::count(), 'active_users' => User::whereNull('blocked_at')->count(), 'blocked_users' => User::whereNotNull('blocked_at')->count(), 'verified_users' => User::whereNotNull('email_verified_at')->count(), 'recent_registrations' => User::where('created_at', '>=', now()->subDays(7))->count(),];

        return ApiResponse::success(['users' => $users, 'roles' => $roles, 'global_stats' => $globalStats,]);
    }

    /**
     * Créer un nouvel utilisateur (POST /users)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['username' => 'required|string|max:50|unique:users', 'email' => 'required|email|unique:users', 'password' => 'required|string|min:8|confirmed', 'first_name' => 'nullable|string|max:255', 'last_name' => 'nullable|string|max:255', 'role_power' => 'required|exists:roles,power', 'avatar' => 'nullable|image|max:2048',]);

        $data = $request->only(['username', 'email', 'first_name', 'last_name', 'role_power']);
        $data['password'] = Hash::make($request->password);

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')->store('avatars');
        }

        $user = User::create($data);

        return ApiResponse::success($user, 'Utilisateur créé avec succès.');
    }

    /**
     * Affichage d'un utilisateur spécifique (GET /users/{user})
     */
    public function show(User $user): JsonResponse
    {
        $user->load(['role', 'themes']);

        // Ajouter des statistiques supplémentaires pour l'admin
        $additionalStats = ['themes_count' => $user->themes()->count(), 'tasks_count' => $user->tasks()->count(), 'completed_tasks_count' => $user->tasks()->where('status', 'done')->count(), 'last_activity' => max($user->last_login_at?->toDateTimeString(), $user->updated_at->toDateTimeString()), 'account_age_days' => $user->created_at->diffInDays(now()), 'themes_as_member' => $user->themeUserPermissions()->where('status', 'active')->count(),];

        return ApiResponse::success(['user' => $user, 'additional_stats' => $additionalStats,]);
    }

    /**
     * Mise à jour d'un utilisateur (PUT /users/{user})
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $request->validate(['username' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($user->user_id, 'user_id')], 'email' => ['required', 'email', Rule::unique('users')->ignore($user->user_id, 'user_id')], 'first_name' => 'nullable|string|max:255', 'last_name' => 'nullable|string|max:255', 'role_power' => 'required|exists:roles,power', 'password' => 'nullable|string|min:8|confirmed', 'avatar' => 'nullable|image|max:2048',]);

        $data = $request->only(['username', 'email', 'first_name', 'last_name', 'role_power']);

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

        return ApiResponse::success($user, 'Utilisateur mis à jour avec succès.');
    }

    /**
     * Supprimer un utilisateur (DELETE /users/{user})
     */
    public function destroy(User $user): JsonResponse
    {
        // Empêcher la suppression de son propre compte
        if ($user->user_id === auth()->user()->user_id) {
            return ApiResponse::error('Vous ne pouvez pas supprimer votre propre compte.', 400);
        }

        if ($user->avatar_path) {
            Storage::delete($user->avatar_path);
        }

        $user->delete();

        return ApiResponse::success(null, 'Utilisateur supprimé avec succès.');
    }

    /**
     * Bloquer un utilisateur (POST /users/{user}/block)
     */
    public function block(User $user): JsonResponse
    {
        if ($user->isBlocked()) {
            return ApiResponse::error('Cet utilisateur est déjà bloqué.', 400);
        }

        // Empêcher de se bloquer soi-même
        if ($user->user_id === auth()->user()->user_id) {
            return ApiResponse::error('Vous ne pouvez pas bloquer votre propre compte.', 400);
        }

        $user->block();
        return ApiResponse::success($user, 'Utilisateur bloqué avec succès.');
    }

    /**
     * Débloquer un utilisateur (POST /users/{user}/unblock)
     */
    public function unblock(User $user): JsonResponse
    {
        if (!$user->isBlocked()) {
            return ApiResponse::error('Cet utilisateur n\'est pas bloqué.', 400);
        }

        $user->unblock();
        return ApiResponse::success($user, 'Utilisateur débloqué avec succès.');
    }
}
