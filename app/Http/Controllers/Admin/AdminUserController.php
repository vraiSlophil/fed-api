<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Role;
use App\Models\User;
use App\Models\ThemeUserPermission;
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
    /**
     * Liste des utilisateurs avec filtres et pagination (GET /users)
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['role']);

        // Recherche générale
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        // Filtrer par rôle
        if ($request->filled('role')) {
            $query->where('role_power', $request->role);
        }

        // Filtrer par statut (blocked/active/unverified)
        if ($request->filled('status')) {
            if ($request->status === 'blocked') {
                $query->whereNotNull('blocked_at');
            } else if ($request->status === 'active') {
                $query->whereNotNull('email_verified_at');
                $query->whereNull('blocked_at');
            } else if ($request->status === 'unverified') {
                $query->whereNull('email_verified_at');
                $query->whereNull('blocked_at');
            } else {
                return ApiResponse::builder()
                    ->error(400, 'Statut invalide. Utilisez "blocked", "active" ou "unverified".')
                    ->json();
            }
        }

        // Filtrage multiple par rôles
        if ($request->filled('roles')) {
            $roles = explode(',', $request->roles);
            $query->whereIn('role_power', $roles);
        }

        // Tri dynamique
        $sortField = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort', 'desc');

        // Colonnes autorisées pour le tri
        $allowedSortFields = [
            'created_at',
            'updated_at',
            'username',
            'email',
            'first_name',
            'last_name',
            'last_login_at',
            'email_verified_at',
            'blocked_at'
        ];

        // Valider le champ de tri
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'created_at';
        }

        // Valider la direction du tri
        $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        // Appliquer le tri
        $query->orderBy($sortField, $sortDirection);

        // Pagination personnalisable
        $perPage = $request->input('per_page', 20);
        $perPage = max(1, min(100, intval($perPage))); // Limiter entre 1 et 100

        $users = $query->paginate($perPage);

        // Structure de réponse complète
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
            // Utiliser le disque 'public' au lieu du disque par défaut
            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user = User::create($data);

        // Envoyer l'email de vérification
        event(new Registered($user));

        return ApiResponse::builder()
            ->success(200, 'Utilisateur créé avec succès. Un email de vérification a été envoyé à l\'utilisateur.')
            ->data($user)
            ->json();
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

        return ApiResponse::builder()
            ->success()
            ->data([
                'user' => $user,
                'additional_stats' => $additionalStats,
            ])
            ->json();
    }

    /**
     * Mise à jour d'un utilisateur (PUT /users/{user})
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'username' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($user->user_id, 'user_id')],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->user_id, 'user_id')],
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
            'role_power'
        ]);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('avatar')) {
            // Supprimer l'ancien avatar s'il existe
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            // Utiliser le disque 'public'
            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        // Vérifier si l'email a été modifié avant la mise à jour
        $emailChanged = $user->email !== $data['email'];

        $user->update($data);

        // Si l'email a été modifié, marquer comme non vérifié et envoyer un email
        if ($emailChanged) {
            $user->email_verified_at = null;
            $user->save();
            $user->sendEmailVerificationNotification();

            return ApiResponse::builder()
                ->success(200, 'Utilisateur mis à jour avec succès. Un email de vérification a été envoyé à la nouvelle adresse.')
                ->data($user)
                ->json();
        }

        return ApiResponse::builder()
            ->success(200, 'Utilisateur mis à jour avec succès.')
            ->data($user)
            ->json();
    }

    /**
     * Supprimer un utilisateur (DELETE /users/{user})
     */
    public function destroy(User $user): JsonResponse
    {
        // Empêcher la suppression de son propre compte
        if ($user->user_id === auth()->user()->user_id) {
            return ApiResponse::builder()
                ->error(400, 'Vous ne pouvez pas supprimer votre propre compte.')
                ->json();
        }

        try {
            // Supprimer l'avatar s'il existe
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            // Forcer la suppression définitive (ignore le soft delete)
            $user->forceDelete();

            return ApiResponse::builder()
                ->success(200, 'Utilisateur supprimé définitivement avec succès.')
                ->json();

        } catch (QueryException $e) {
            // Erreur de contrainte de clé étrangère
            if ($e->getCode() === '23000') {
                return ApiResponse::builder()
                    ->error(409, 'Impossible de supprimer cet utilisateur car il a des données associées. Supprimez d\'abord ses thèmes, tâches et permissions.')
                    ->json();
            }

            return ApiResponse::builder()
                ->error(500, 'Erreur lors de la suppression : ' . $e->getMessage())
                ->json();

        } catch (Exception $e) {
            return ApiResponse::builder()
                ->error(500, 'Erreur inattendue lors de la suppression : ' . $e->getMessage())
                ->json();
        }
    }

//    /**
//     * Supprimer les relations de l'utilisateur avant suppression définitive
//     */
//    private function deleteUserRelations(User $user): void
//    {
//        // Supprimer les tokens d'authentification
//        $user->tokens()->delete();
//
//        // Supprimer les métriques utilisateur
//        $user->metrics()->delete();
//
//        // Transférer ou supprimer les thèmes (selon votre logique métier)
//        $user->themes()->delete(); // ou les transférer à un autre utilisateur
//
//        // Supprimer les tâches
//        $user->tasks()->delete();
//
//        // Supprimer les permissions sur les thèmes
//        ThemeUserPermission::where('user_id', $user->user_id)->delete();
//    }

    /**
     * Bloquer un utilisateur (POST /users/{user}/block)
     */
    public function block(User $user): JsonResponse
    {
        if ($user->blocked_at !== null) {
            return ApiResponse::builder()
                ->error(400, 'Cet utilisateur est déjà bloqué.')
                ->json();
        }

        // Empêcher de se bloquer soi-même
        if ($user->user_id === auth()->user()->user_id) {
            return ApiResponse::builder()
                ->error(400, 'Vous ne pouvez pas bloquer votre propre compte.')
                ->json();
        }

        $user->update(['blocked_at' => now()]);
        return ApiResponse::builder()
            ->success(200, 'Utilisateur bloqué avec succès.')
            ->data($user)
            ->json();
    }

    /**
     * Débloquer un utilisateur (POST /users/{user}/unblock)
     */
    public function unblock(User $user): JsonResponse
    {
        if ($user->blocked_at === null) {
            return ApiResponse::builder()
                ->error(400, 'Cet utilisateur n\'est pas bloqué.')
                ->json();
        }

        $user->update(['blocked_at' => null]);
        return ApiResponse::builder()
            ->success(200, 'Utilisateur débloqué avec succès.')
            ->data($user)
            ->json();
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
