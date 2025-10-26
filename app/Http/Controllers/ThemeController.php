<?php

namespace App\Http\Controllers;

use App\Models\Theme;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Auth;

class ThemeController extends Controller
{
    /**
     * Retourne la liste des thèmes dont l'utilsateur est le propriétaire, mais aussi les thèmes dans lesquels il est invité.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->user_id;
        $playgroundId = $request->query('playground_id');

        // Thèmes possédés par l'utilisateur
        $ownedThemes = Theme::where('owner_id', $userId)
            ->when($playgroundId, function($query, $playgroundId) {
                $query->where('playground_id', $playgroundId);
            })
            ->get();

        // Thèmes partagés avec l'utilisateur
        $invitedThemes = Theme::whereHas('themeUserPermissions', function ($query) use ($userId, $playgroundId) {
            $query->where('user_id', $userId)
                ->where('can_view', true)
                ->where('status', 'active')
                // Filtrer par playground cible si spécifié
                ->when($playgroundId, function($q, $playgroundId) {
                    $q->where('target_playground_id', $playgroundId);
                });
        })
            ->whereNot('owner_id', $userId)
            ->with(['themeUserPermissions' => function($query) use ($userId) {
                $query->where('user_id', $userId);
            }])
            ->get();

        $invitedThemes->each(function ($theme) {
            $permission = $theme->themeUserPermissions->first();
            $theme->permissions = $permission;
            $theme->target_playground_id = $permission->target_playground_id;
            unset($theme->themeUserPermissions);
        });

        $allThemes = $ownedThemes->concat($invitedThemes);

        return ApiResponse::builder()
            ->success()
            ->data([
                'themes' => $allThemes
            ])
            ->json();
    }

    /**
     * Créer un nouveau thème.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->user_id;

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'color' => 'required|string|size:7',
            'playground_id' => 'required|string|max:100',
        ]);

        $theme = Theme::create([
            'owner_id' => $userId,
            'title' => $validated['title'],
            'color' => $validated['color'],
            'playground_id' => $validated['playground_id'],
        ]);

        return ApiResponse::builder()
            ->success(201)
            ->data([
                'theme' => $theme
            ])
            ->json();
    }

    /**
     * Afficher un thème spécifique.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->user_id;

        // Récupérer le thème s'il appartient à l'utilisateur ou s'il a les permissions nécessaires
        $theme = Theme::where('theme_id', $id)
            ->where(function($query) use ($userId) {
                $query->where('owner_id', $userId)
                    ->orWhereHas('themeUserPermissions', function($q) use ($userId) {
                        $q->where('user_id', $userId)
                            ->where('can_view', true)
                            ->where('status', 'active');
                    });
            })
            ->firstOrFail();

        // Ajouter les permissions si l'utilisateur n'est pas le propriétaire
        if (!$theme->isOwnedBy($userId)) {
            $permissions = $theme->getPermissionsFor($userId);
            $theme->permissions = $permissions;
        }

        return ApiResponse::builder()
            ->success()
            ->data([
                'theme' => $theme
            ])
            ->json();
    }

    /**
     * Mettre à jour un thème existant.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->user_id;

        // Récupérer le thème s'il appartient à l'utilisateur ou s'il a les permissions nécessaires
        $theme = Theme::where('theme_id', $id)
            ->where(function($query) use ($userId) {
                $query->where('owner_id', $userId)
                    ->orWhereHas('themeUserPermissions', function($q) use ($userId) {
                        $q->where('user_id', $userId)
                            ->where('can_update_theme', true)
                            ->where('status', 'active');
                    });
            })
            ->firstOrFail();

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:150',
            'color' => 'sometimes|required|string|size:7',
        ]);

        $theme->update($validated);

        // Ajouter les permissions si l'utilisateur n'est pas le propriétaire
        if (!$theme->isOwnedBy($userId)) {
            $permissions = $theme->getPermissionsFor($userId);
            $theme->permissions = $permissions;
        }

        return ApiResponse::builder()
            ->success()
            ->data([
                'theme' => $theme
            ])
            ->json();
    }

    /**
     * Supprimer un thème.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->user_id;
        $theme = Theme::where('owner_id', $userId)
            ->where('theme_id', $id)
            ->firstOrFail();

        $theme->delete();

        return ApiResponse::builder()
            ->success(204)
            ->json();
    }
}
