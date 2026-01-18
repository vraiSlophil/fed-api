<?php

namespace App\Http\Controllers;

use App\Models\Theme;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Auth;

class ThemeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->user_id;
        $playgroundId = $request->query('playground_id');

        $ownedThemes = Theme::where('owner_id', $userId)
            ->when($playgroundId, function ($query, $playgroundId) {
                $query->where('playground_id', $playgroundId);
            })
            ->get();

        $invitedThemes = Theme::whereHas('themeUserPermissions', function ($query) use ($userId, $playgroundId) {
            $query->where('user_id', $userId)
                ->where('can_view', true)
                ->where('status', 'active')
                ->when($playgroundId, function ($q, $playgroundId) {
                    $q->where('target_playground_id', $playgroundId);
                });
        })
            ->whereNot('owner_id', $userId)
            ->with(['themeUserPermissions' => function ($query) use ($userId) {
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
            ->messageCode('theme.list.success')
            ->data([
                'themes' => $allThemes
            ])
            ->json();
    }

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
            ->messageCode('theme.create.success')
            ->data([
                'theme' => $theme
            ])
            ->json();
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->user_id;

        $theme = Theme::where('theme_id', $id)
            ->where(function ($query) use ($userId) {
                $query->where('owner_id', $userId)
                    ->orWhereHas('themeUserPermissions', function ($q) use ($userId) {
                        $q->where('user_id', $userId)
                            ->where('can_view', true)
                            ->where('status', 'active');
                    });
            })
            ->firstOrFail();

        if (!$theme->isOwnedBy($userId)) {
            $permissions = $theme->getPermissionsFor($userId);
            $theme->permissions = $permissions;
        }

        return ApiResponse::builder()
            ->success()
            ->messageCode('theme.show.success')
            ->data([
                'theme' => $theme
            ])
            ->json();
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->user_id;

        $theme = Theme::where('theme_id', $id)
            ->where(function ($query) use ($userId) {
                $query->where('owner_id', $userId)
                    ->orWhereHas('themeUserPermissions', function ($q) use ($userId) {
                        $q->where('user_id', $userId)
                            ->where('can_update_theme', true)
                            ->where('status', 'active');
                    });
            })
            ->firstOrFail();

        $validated = $request->validate([
            'title' => 'sometimes|string|max:150',
            'color' => 'sometimes|string|size:7',
            'playground_id' => 'sometimes|string|max:100',
        ]);

        $theme->update($validated);

        if (!$theme->isOwnedBy($userId)) {
            $permissions = $theme->getPermissionsFor($userId);
            $theme->permissions = $permissions;
        }

        return ApiResponse::builder()
            ->success()
            ->messageCode('theme.update.success')
            ->data([
                'theme' => $theme
            ])
            ->json();
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->user_id;
        $theme = Theme::where('owner_id', $userId)
            ->where('theme_id', $id)
            ->firstOrFail();

        $theme->delete();

        return ApiResponse::builder()
            ->success(204)
            ->messageCode('theme.delete.success')
            ->json();
    }
}
