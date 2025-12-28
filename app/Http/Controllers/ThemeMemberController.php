<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Mail\ThemeInvitation;
use App\Models\Playground;
use App\Models\Theme;
use App\Models\ThemeUserPermission;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ThemeMemberController extends Controller
{
    /**
     * @var User|mixed Utilisateur authentifié présent dans la requête
     */
    protected User $user;

    /**
     * Constructeur pour initialiser l'utilisateur authentifié
     *
     * @param  Request  $request  Requête HTTP contenant l'utilisateur authentifié et d'autres données
     */
    public function __construct(Request $request)
    {
        $this->user = $request->user();
    }

    /**
     * Rechercher des utilisateurs pour les inviter à un thème
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'required|string|min:3',
            'theme_id' => 'required|uuid|exists:themes,theme_id',
        ]);

        $search = $request->search;
        $themeId = $request->theme_id;

        $theme = Theme::findOrFail($themeId);
        $ownerId = $theme->owner_id;

        // Normaliser la recherche pour ignorer les accents et la casse
        $normalizedSearch = $this->normalizeString($search);

        // Rechercher les utilisateurs par nom d'utilisateur ou email
        // N'inclure que les utilisateurs dont l'email est vérifié
        $users = User::whereNotNull('email_verified_at')
            ->where('user_id', '!=', $ownerId)
            ->where(function ($query) use ($normalizedSearch) {
                $query->where('username', 'like', "%{$normalizedSearch}%")
                    ->orWhere('email', 'like', "%{$normalizedSearch}%")
                    ->orWhere('first_name', 'like', "%{$normalizedSearch}%")
                    ->orWhere('last_name', 'like', "%{$normalizedSearch}%");
            })
            ->limit(10)
            ->get(['user_id', 'username', 'email', 'first_name', 'last_name', 'avatar_path']);

        // Formater les résultats
        $formattedUsers = $users->map(function ($user) {
            return [
                'user_id' => $user->user_id,
                'username' => $user->username,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'avatar_path' => $user->avatar_path,
            ];
        });

        return ApiResponse::builder()
            ->success()
            ->data([
                'users' => $formattedUsers,
            ])
            ->json();
    }

    /**
     * Liste des membres d'un thème
     */
    public function listMembers(string $themeId): JsonResponse
    {
        $theme = $this->getThemeOrFail($themeId);

        // Récupérer le propriétaire du thème
        $owner = $theme->owner;

        // Récupérer tous les membres invités/actifs/inactifs
        $permissions = $theme->themeUserPermissions()
            ->with('user')
            ->get();

        // Formater la liste des membres
        $members = $permissions->map(function ($permission) {
            return [
                'user_id' => $permission->user->user_id,
                'username' => $permission->user->username,
                'email' => $permission->user->email,
                'first_name' => $permission->user->first_name,
                'last_name' => $permission->user->last_name,
                'avatar_path' => $permission->user->avatar_path,
                'status' => $permission->status,
                'invited_at' => $permission->invited_at,
                'permissions' => [
                    'can_view' => $permission->can_view,
                    'can_update_theme' => $permission->can_update_theme,
                    'can_add_task' => $permission->can_add_task,
                    'can_edit_task' => $permission->can_edit_task,
                    'can_delete_task' => $permission->can_delete_task,
                    'can_validate_task' => $permission->can_validate_task,
                ],
            ];
        });

        // Ajouter le propriétaire au début de la liste
        $ownerData = [
            'user_id' => $owner->user_id,
            'username' => $owner->username,
            'email' => $owner->email,
            'first_name' => $owner->first_name,
            'last_name' => $owner->last_name,
            'avatar_path' => $owner->avatar_path,
            'status' => 'owner', // Statut spécial pour le propriétaire
            'invited_at' => null,
            'permissions' => [
                'can_view' => true,
                'can_update_theme' => true,
                'can_add_task' => true,
                'can_edit_task' => true,
                'can_delete_task' => true,
                'can_validate_task' => true,
            ],
        ];

        // Placer le propriétaire en premier
        $allMembers = collect([$ownerData])->merge($members);

        return ApiResponse::builder()
            ->success()
            ->data([
                'members' => $allMembers,
            ])
            ->json();
    }

    /**
     * Inviter un utilisateur à rejoindre un thème
     */
    public function inviteUser(Request $request, string $themeId): JsonResponse
    {
        $theme = $this->getThemeOrFail($themeId);

        $validated = $request->validate([
            'user_id' => 'required|uuid|exists:users,user_id',
            'can_view' => 'required|boolean',
            'can_update_theme' => 'required|boolean',
            'can_add_task' => 'required|boolean',
            'can_edit_task' => 'required|boolean',
            'can_delete_task' => 'required|boolean',
            'can_validate_task' => 'required|boolean',
        ]);

        // Vérifier que l'utilisateur n'est pas le propriétaire du thème

        if ($theme->owner_id === $validated['user_id']) {
            return ApiResponse::builder()
                ->error(403, 'Vous ne pouvez pas inviter le propriétaire du thème.')
                ->json();
        }

        // Vérifier que l'utilisateur n'est pas déjà membre du thème
        if (ThemeUserPermission::where('theme_id', $themeId)
            ->where('user_id', $validated['user_id'])
            ->first()) {
            return ApiResponse::builder()
                ->error(409, 'Cet utilisateur est déjà membre de ce thème.')
                ->json();
        }

        // Créer les permissions pour l'utilisateur
        $permission = ThemeUserPermission::create([
            'theme_id' => $themeId,
            'user_id' => $validated['user_id'],
            'can_view' => $validated['can_view'],
            'can_update_theme' => $validated['can_update_theme'],
            'can_add_task' => $validated['can_add_task'],
            'can_edit_task' => $validated['can_edit_task'],
            'can_delete_task' => $validated['can_delete_task'],
            'can_validate_task' => $validated['can_validate_task'],
            'status' => 'invited',
            'invited_at' => now(),
        ]);

        // Récupérer l'utilisateur invité
        $invitedUser = User::findOrFail($validated['user_id']);

        $acceptLink = URL::temporarySignedRoute(
            'theme.accept-invitation',
            now()->addDays(7),
            [
                'theme_id' => $themeId,
                'user_id' => $invitedUser->user_id,
                'token' => Str::random(40),
                'action' => 'accept',
            ]
        );

        $declineLink = URL::temporarySignedRoute(
            'theme.accept-invitation',
            now()->addDays(7),
            [
                'theme_id' => $themeId,
                'user_id' => $invitedUser->user_id,
                'token' => Str::random(40),
                'action' => 'decline',
            ]
        );

        // Envoyer l'e-mail d'invitation
        try {
            Mail::to($invitedUser->email)
                ->send(new ThemeInvitation(
                    $theme,
                    $this->user,
                    $invitedUser,
                    $acceptLink,
                    $declineLink
                ));
        } catch (Exception $e) {
            //            Log::error('Erreur lors de l\'envoi de l\'email d\'invitation', [
            //                'error' => $e->getMessage(),
            //                'theme_id' => $themeId,
            //                'user_id' => $invitedUser->user_id,
            //            ]);

            // Supprimer la permission si l'email échoue
            $permission->delete();

            return ApiResponse::builder()
                ->error(500, 'Erreur lors de l\'envoi de l\'email d\'invitation. Veuillez réessayer.')
                ->json();
        }

        return ApiResponse::builder()
            ->success(201, "Invitation envoyée à {$invitedUser->email}")
            ->data([
                'invitation' => [
                    'user_id' => $invitedUser->user_id,
                    'username' => $invitedUser->username,
                    'email' => $invitedUser->email,
                    'first_name' => $invitedUser->first_name,
                    'last_name' => $invitedUser->last_name,
                    'status' => 'invited',
                    'invited_at' => $permission->invited_at,
                ],
            ])
            ->json();
    }

    /**
     * Mettre à jour les permissions d'un membre
     */
    public function updateMemberPermissions(Request $request, string $themeId, string $userId): JsonResponse
    {
        $theme = $this->getThemeOrFail($themeId);

        $validated = $request->validate([
            'can_view' => 'required|boolean',
            'can_update_theme' => 'required|boolean',
            'can_add_task' => 'required|boolean',
            'can_edit_task' => 'required|boolean',
            'can_delete_task' => 'required|boolean',
            'can_validate_task' => 'required|boolean',
        ]);

        // Récupérer la permission
        $permission = ThemeUserPermission::where('theme_id', $themeId)
            ->where('user_id', $userId)
            ->firstOrFail();

        // Mettre à jour les permissions
        $permission->update([
            'can_view' => $validated['can_view'],
            'can_update_theme' => $validated['can_update_theme'],
            'can_add_task' => $validated['can_add_task'],
            'can_edit_task' => $validated['can_edit_task'],
            'can_delete_task' => $validated['can_delete_task'],
            'can_validate_task' => $validated['can_validate_task'],
        ]);

        return ApiResponse::builder()
            ->success(200, 'Permissions mises à jour avec succès.')
            ->data([
                'permissions' => [
                    'can_view' => $permission->can_view,
                    'can_update_theme' => $permission->can_update_theme,
                    'can_add_task' => $permission->can_add_task,
                    'can_edit_task' => $permission->can_edit_task,
                    'can_delete_task' => $permission->can_delete_task,
                    'can_validate_task' => $permission->can_validate_task,
                ],
            ])
            ->json();
    }

    /**
     * Désactiver un membre
     */
    public function deactivateMember(string $themeId, string $userId): JsonResponse
    {
        $theme = $this->getThemeOrFail($themeId);

        // Vérifier que l'utilisateur n'est pas le propriétaire
        if ($theme->owner_id === $userId) {
            return ApiResponse::builder()
                ->error(400, 'Vous ne pouvez pas désactiver le propriétaire du thème.')
                ->json();
        }

        // Récupérer la permission
        $permission = ThemeUserPermission::where('theme_id', $themeId)
            ->where('user_id', $userId)
            ->firstOrFail();

        // Mettre à jour le statut
        $permission->status = 'revoked';
        $permission->save();

        return ApiResponse::builder()
            ->success(200, 'Membre désactivé avec succès.')
            ->json();
    }

    /**
     * Réactiver un membre
     */
    public function reactivateMember(string $themeId, string $userId): JsonResponse
    {
        // Récupérer la permission
        $permission = ThemeUserPermission::where('theme_id', $themeId)
            ->where('user_id', $userId)
            ->where('status', 'revoked')
            ->firstOrFail();

        // Mettre à jour le statut
        $permission->status = 'active';
        $permission->save();

        return ApiResponse::builder()
            ->success(200, 'Membre réactivé avec succès.')
            ->json();
    }

    /**
     * Supprimer un membre
     */
    public function removeMember(string $themeId, string $userId): JsonResponse
    {
        $theme = $this->getThemeOrFail($themeId);

        // Vérifier que l'utilisateur n'est pas le propriétaire
        if ($theme->owner_id === $userId) {
            return ApiResponse::builder()
                ->error(400, 'Vous ne pouvez pas supprimer le propriétaire du thème.')
                ->json();
        }

        // Récupérer la permission
        $permission = ThemeUserPermission::where('theme_id', $themeId)
            ->where('user_id', $userId)
            ->firstOrFail();

        // Supprimer la permission
        $permission->delete();

        return ApiResponse::builder()
            ->success(200, 'Membre supprimé avec succès.')
            ->json();
    }

    /**
     * Quitter un thème (pour l'utilisateur connecté)
     */
    public function leaveTheme(string $themeId): JsonResponse
    {
        $userId = $this->user->user_id;

        // Vérifier que l'utilisateur n'est pas le propriétaire
        $theme = Theme::findOrFail($themeId);
        if ($theme->owner_id === $userId) {
            return ApiResponse::builder()
                ->error(400, 'En tant que propriétaire, vous ne pouvez pas quitter ce thème. Vous devez le supprimer ou transférer la propriété.')
                ->json();
        }

        // Récupérer la permission
        $permission = ThemeUserPermission::where('theme_id', $themeId)
            ->where('user_id', $userId)
            ->firstOrFail();

        // Supprimer la permission
        $permission->delete();

        return ApiResponse::builder()
            ->success(200, 'Vous avez quitté le thème avec succès.')
            ->json();
    }

    /**
     * Déplacer un thème partagé vers un autre playground
     */
    public function moveToPlayground(Request $request, string $themeId): JsonResponse
    {
        $userId = $request->user()->user_id;

        $validated = $request->validate([
            'target_playground_id' => 'required|uuid|exists:playgrounds,playground_id',
        ]);

        // Vérifier que le playground appartient à l'utilisateur
        $playground = Playground::where('playground_id', $validated['target_playground_id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        // Récupérer la permission
        $permission = ThemeUserPermission::where('theme_id', $themeId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->firstOrFail();

        $permission->update([
            'target_playground_id' => $validated['target_playground_id'],
        ]);

        return ApiResponse::builder()
            ->success(200, 'Thème déplacé avec succès')
            ->data([
                'permission' => $permission->fresh(['theme', 'targetPlayground']),
            ])
            ->json();
    }

    /**
     * Récupérer un thème et vérifier que l'utilisateur actuel en est le propriétaire
     */
    private function getThemeOrFail(string $themeId): Theme
    {
        $userId = $this->user->user_id;

        return Theme::where('theme_id', $themeId)
            ->where('owner_id', $userId)
            ->firstOrFail();
    }

    /**
     * Normalise une chaîne en retirant les accents et en la convertissant en minuscules
     */
    private function normalizeString(string $string): string
    {
        // Convertir en minuscules
        $string = mb_strtolower($string, 'UTF-8');

        // Supprimer les accents
        return transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $string);
    }
}
