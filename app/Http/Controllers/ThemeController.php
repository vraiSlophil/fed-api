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

        $ownedThemes = Theme::where('owner_id', $userId)->get();

        // Récupérer les thèmes dans lesquels l'utilisateur est invité avec can_view=true et status=active
        // en incluant les permissions associées
        $invitedThemes = Theme::whereHas('themeUserPermissions', function ($query) use ($userId) {
            $query->where('user_id', $userId)
                ->where('can_view', true)
                ->where('status', 'active');
        })
            ->whereNot('owner_id', $userId)
            ->with(['themeUserPermissions' => function($query) use ($userId) {
                $query->where('user_id', $userId);
            }])
            ->get();

        // Ajouter le champ permission pour chaque thème invité
        $invitedThemes->each(function ($theme) {
            // Récupérer la première permission (il n'y en aura qu'une par utilisateur et thème)
            $permission = $theme->themeUserPermissions->first();

            // Ajouter les permissions comme attribut au thème et supprimer la relation complète
            $theme->permission = $permission;
            unset($theme->themeUserPermissions);
        });

        // Combiner les deux collections
        $allThemes = $ownedThemes->concat($invitedThemes);

        return ApiResponse::success([
            'themes' => $allThemes
        ]);

    }

    /**
     * Créer un nouveau thème.
     */
    public function store(Request $request): JsonResponse
    {
        $userId = $request->user()->user_id;

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'color' => 'required|string|size:7',
        ]);

        $theme = Theme::create([
            'owner_id' => $userId,
            'title' => $validated['title'],
            'color' => $validated['color'],
        ]);

        return ApiResponse::success([
            'theme' => $theme
        ], 201);
    }

    /**
     * Afficher un thème spécifique.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->user_id;

        $theme = Theme::where('owner_id', $userId)
            ->where('theme_id', $id)
            ->firstOrFail();

        return ApiResponse::success([
            'theme' => $theme
        ]);
    }

    /**
     * Mettre à jour un thème existant.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->user_id;

        $theme = Theme::where('owner_id', $userId)
            ->where('theme_id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:150',
            'color' => 'sometimes|required|string|size:7',
        ]);

        $theme->update($validated);

        return ApiResponse::success([
            'theme' => $theme
        ]);
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

        return ApiResponse::success(null, 204);
    }
}
