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
     * Afficher une liste des thèmes.
     */
    public function index(): JsonResponse
    {
        $themes = Theme::where('owner_id', Auth::id())->get();
        
        return ApiResponse::success([
            'themes' => $themes
        ]);
    }

    /**
     * Créer un nouveau thème.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'color' => 'required|string|size:7',
        ]);

        $theme = Theme::create([
            'owner_id' => Auth::id(),
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
    public function show(string $id): JsonResponse
    {
        $theme = Theme::where('owner_id', Auth::id())
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
        $theme = Theme::where('owner_id', Auth::id())
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
    public function destroy(string $id): JsonResponse
    {
        $theme = Theme::where('owner_id', Auth::id())
            ->where('theme_id', $id)
            ->firstOrFail();
            
        $theme->delete();
        
        return ApiResponse::success(null, 204);
    }
}
