<?php

namespace App\Http\Controllers;

use App\Models\Playground;
use App\Models\Theme;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PlaygroundController extends Controller
{
    /**
     * Récupérer tous les playgrounds de l'utilisateur connecté
     */
    public function index(Request $request): JsonResponse
    {
        $playgrounds = $request->user()->playgrounds()
            ->withCount(['themes'])
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return ApiResponse::success([
            'playgrounds' => $playgrounds
        ]);
    }

    /**
     * Récupérer un playground avec toutes ses données
     */
    public function show(Request $request, string $playgroundId): JsonResponse
    {
        try {
            $playground = Playground::where('playground_id', $playgroundId)
                ->where('user_id', $request->user()->user_id)
                ->firstOrFail();

            // Récupérer toutes les données du playground
            $playgroundData = $this->getPlaygroundCompleteData($playground);

            return ApiResponse::success($playgroundData);

        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Playground non trouvé', 404);
        }
    }

    /**
     * Créer un nouveau playground
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'slug' => 'nullable|string|max:140',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|size:7|regex:/^#[0-9A-F]{6}$/i',
            'background_color' => 'nullable|string|size:7|regex:/^#[0-9A-F]{6}$/i',
            'style' => 'nullable|array',
            'is_default' => 'boolean'
        ]);

        $playground = $request->user()->playgrounds()->create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? \Str::slug($validated['name']),
            'icon' => $validated['icon'] ?? null,
            'color' => $validated['color'] ?? '#6366F1',
            'background_color' => $validated['background_color'] ?? null,
            'style' => $validated['style'] ?? null,
            'is_default' => $validated['is_default'] ?? false,
        ]);

        // Si défini comme par défaut, mettre à jour les autres
        if ($playground->is_default) {
            $playground->setAsDefault();
        }

        return ApiResponse::success([
            'playground' => $playground
        ], 'Playground créé avec succès', 201);
    }

    /**
     * Mettre à jour un playground
     */
    public function update(Request $request, string $playgroundId): JsonResponse
    {
        try {
            $playground = Playground::where('playground_id', $playgroundId)
                ->where('user_id', $request->user()->user_id)
                ->firstOrFail();

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:120',
                'slug' => 'nullable|string|max:140',
                'icon' => 'nullable|string|max:50',
                'color' => 'nullable|string|size:7|regex:/^#[0-9A-F]{6}$/i',
                'background_color' => 'nullable|string|size:7|regex:/^#[0-9A-F]{6}$/i',
                'style' => 'nullable|array',
                'is_default' => 'boolean'
            ]);

            $playground->update($validated);

            // Si défini comme par défaut, mettre à jour les autres
            if (isset($validated['is_default']) && $validated['is_default']) {
                $playground->setAsDefault();
            }

            return ApiResponse::success([
                'playground' => $playground->fresh()
            ], 'Playground mis à jour avec succès');

        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Playground non trouvé', 404);
        }
    }

    /**
     * Supprimer un playground
     */
    public function destroy(Request $request, string $playgroundId): JsonResponse
    {
        try {
            $playground = Playground::where('playground_id', $playgroundId)
                ->where('user_id', $request->user()->user_id)
                ->firstOrFail();

            // Empêcher la suppression du playground par défaut s'il est le seul
            if ($playground->is_default) {
                $playgroundCount = $request->user()->playgrounds()->count();
                if ($playgroundCount === 1) {
                    return ApiResponse::error('Impossible de supprimer le dernier playground', 400);
                }
            }

            $playground->delete();

            return ApiResponse::success(null, 'Playground supprimé avec succès');

        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Playground non trouvé', 404);
        }
    }

    /**
     * Définir un playground comme par défaut
     */
    public function setAsDefault(Request $request, string $playgroundId): JsonResponse
    {
        try {
            $playground = Playground::where('playground_id', $playgroundId)
                ->where('user_id', $request->user()->user_id)
                ->firstOrFail();

            $playground->setAsDefault();

            // Mettre à jour l'utilisateur pour définir ce playground comme actif
            $request->user()->update(['active_playground_id' => $playground->playground_id]);

            return ApiResponse::success([
                'playground' => $playground->fresh()
            ], 'Playground défini comme par défaut');

        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Playground non trouvé', 404);
        }
    }

    /**
     * Récupérer les statistiques d'un playground
     */
    public function stats(Request $request, string $playgroundId): JsonResponse
    {
        try {
            $playground = Playground::where('playground_id', $playgroundId)
                ->where('user_id', $request->user()->user_id)
                ->firstOrFail();

            $stats = [
                'themes' => [
                    'total' => $playground->themes()->count(),
                    'private' => $playground->themes()->where('visibility', 'private')->count(),
                    'shared' => $playground->themes()->where('visibility', 'shared')->count(),
                    'public' => $playground->themes()->where('visibility', 'public')->count(),
                ],
                'tasks' => [
                    'total' => Task::whereHas('theme', function($query) use ($playground) {
                        $query->where('playground_id', $playground->playground_id);
                    })->count(),
                    'todo' => Task::whereHas('theme', function($query) use ($playground) {
                        $query->where('playground_id', $playground->playground_id);
                    })->where('status', 'todo')->count(),
                    'in_progress' => Task::whereHas('theme', function($query) use ($playground) {
                        $query->where('playground_id', $playground->playground_id);
                    })->where('status', 'in_progress')->count(),
                    'done' => Task::whereHas('theme', function($query) use ($playground) {
                        $query->where('playground_id', $playground->playground_id);
                    })->where('status', 'done')->count(),
                ],
                'completion_rate' => $this->calculateCompletionRate($playground),
                'recent_activity' => $this->getRecentActivity($playground)
            ];

            return ApiResponse::success([
                'playground' => $playground,
                'stats' => $stats
            ]);

        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Playground non trouvé', 404);
        }
    }

    /**
     * Récupérer toutes les données complètes d'un playground
     */
    private function getPlaygroundCompleteData(Playground $playground): array
    {
        return [
            'playground' => $playground,
            'themes' => $playground->themes()
                ->with([
                    'tasks' => function($query) {
                        $query->orderBy('position')->orderBy('created_at');
                    },
                    'themeUserPermissions.user:user_id,username,first_name,last_name'
                ])
                ->withCount(['tasks'])
                ->get(),
            'stats' => [
                'themes_count' => $playground->themes()->count(),
                'tasks_count' => Task::whereHas('theme', function($query) use ($playground) {
                    $query->where('playground_id', $playground->playground_id);
                })->count(),
                'completed_tasks_count' => Task::whereHas('theme', function($query) use ($playground) {
                    $query->where('playground_id', $playground->playground_id);
                })->where('status', 'done')->count(),
                'completion_rate' => $this->calculateCompletionRate($playground),
            ],
            'recent_activity' => $this->getRecentActivity($playground)
        ];
    }

    /**
     * Calculer le taux de completion des tâches du playground
     */
    private function calculateCompletionRate(Playground $playground): float
    {
        $totalTasks = Task::whereHas('theme', function($query) use ($playground) {
            $query->where('playground_id', $playground->playground_id);
        })->count();

        if ($totalTasks === 0) return 0.0;

        $completedTasks = Task::whereHas('theme', function($query) use ($playground) {
            $query->where('playground_id', $playground->playground_id);
        })->where('status', 'done')->count();

        return round(($completedTasks / $totalTasks) * 100, 2);
    }

    /**
     * Récupérer l'activité récente du playground
     */
    private function getRecentActivity(Playground $playground): array
    {
        $recentTasks = Task::whereHas('theme', function($query) use ($playground) {
            $query->where('playground_id', $playground->playground_id);
        })
            ->with(['theme:theme_id,title', 'user:user_id,username'])
            ->latest('updated_at')
            ->take(10)
            ->get();

        $recentThemes = $playground->themes()
            ->with(['owner:user_id,username'])
            ->latest('updated_at')
            ->take(5)
            ->get();

        return [
            'recent_tasks' => $recentTasks,
            'recent_themes' => $recentThemes
        ];
    }
}
