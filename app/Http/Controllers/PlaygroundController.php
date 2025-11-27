<?php

namespace App\Http\Controllers;

use App\Models\Playground;;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Utils\PaginationUtil;
use Symfony\Component\Translation\Exception\NotFoundResourceException;

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
            ->orderBy('created_at', 'asc')
            ->get();

        return ApiResponse::builder()
            ->success()
            ->data([
                'playgrounds' => $playgrounds
            ])
            ->json();
    }

    /**
     * Récupérer un playground (métadonnées uniquement, sans thèmes complets)
     *
     * Cette route est utilisée par le front pour récupérer la méta d'un playground
     * à partir de son UUID. Si l'identifiant n'est pas un UUID valide, on renvoie 404
     * afin d'éviter une erreur de type dans PostgreSQL.
     */
    public function show(Request $request, string $playgroundId): JsonResponse
    {
        // Si l'identifiant n'est pas un UUID valide, renvoyer 404 directement
        if (! Str::isUuid($playgroundId)) {
            return ApiResponse::builder()
                ->error(404, 'Playground non trouvé')
                ->json();
        }

        try {
            $playground = $this->findPlaygroundForUserById($playgroundId, $request->user()->user_id, withThemesCount: true);

            return ApiResponse::builder()
                ->success()
                ->data([
                    'playground' => $playground,
                ])
                ->json();

        } catch (ModelNotFoundException $e) {
            return ApiResponse::builder()
                ->error(404, 'Playground non trouvé')
                ->json();
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
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
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

        return ApiResponse::builder()
            ->success(201, 'Playground créé avec succès')
            ->data([
                'playground' => $playground
            ])
            ->json();
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

            return ApiResponse::builder()
                ->success(200, 'Playground mis à jour avec succès')
                ->data([
                    'playground' => $playground->fresh()
                ])
                ->json();

        } catch (ModelNotFoundException $e) {
            return ApiResponse::builder()
                ->error(404, 'Playground non trouvé')
                ->json();
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
                    return ApiResponse::builder()
                        ->error(400, 'Impossible de supprimer le dernier playground')
                        ->json();
                }
            }

            $playground->delete();

            return ApiResponse::builder()
                ->success(200, 'Playground supprimé avec succès')
                ->json();

        } catch (ModelNotFoundException $e) {
            return ApiResponse::builder()
                ->error(404, 'Playground non trouvé')
                ->json();
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

            return ApiResponse::builder()
                ->success(200, 'Playground défini comme par défaut')
                ->data([
                    'playground' => $playground->fresh()
                ])
                ->json();

        } catch (ModelNotFoundException $e) {
            return ApiResponse::builder()
                ->error(404, 'Playground non trouvé')
                ->json();
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

            return ApiResponse::builder()
                ->success()
                ->data([
                    'playground' => $playground,
                    'stats' => $stats
                ])
                ->json();

        } catch (ModelNotFoundException $e) {
            return ApiResponse::builder()
                ->error(404, 'Playground non trouvé')
                ->json();
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
                'completion_rate' => $this->calculateCompletionRate($playground)
            ],
            'recent_activity' => $this->getRecentActivity($playground)
        ];
    }

    private function calculateCompletionRate(Playground $playground): float
    {
        $totalTasks = $this->getTasksQueryForPlayground($playground)->count();

        if ($totalTasks === 0) {
            return 0.0;
        }

        $completedTasks = $this->getTasksQueryForPlayground($playground)
            ->where('status', 'done')
            ->count();

        return (float) number_format(($completedTasks / $totalTasks) * 100.0, 2, '.', '');
    }

    private function getTasksQueryForPlayground(Playground $playground)
    {
        return Task::whereHas('theme', function($query) use ($playground) {
            $query->where('playground_id', $playground->playground_id);
        });
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

    /**
     * Récupérer les thèmes d'un playground avec pagination (par ID)
     */
    public function themes(Request $request, string $playgroundId): JsonResponse
    {
        try {
            $playground = $this->findPlaygroundForUserById($playgroundId, $request->user()->user_id);

            $paginator = $this->paginatePlaygroundThemes($playground->themes(), $request);

            return ApiResponse::builder()
                ->success()
                ->data([
                    'themes' => $paginator['items'],
                    'pagination' => $paginator['pagination'],
                ])
                ->json();
        } catch (ModelNotFoundException $e) {
            return ApiResponse::builder()
                ->error(404, 'Playground non trouvé')
                ->json();
        }
    }

    /**
     * Récupérer un playground par slug (métadonnées uniquement)
     */
    public function showBySlug(Request $request, string $slug): JsonResponse
    {
        try {
            $playground = $this->findPlaygroundForUserBySlug($slug, $request->user()->user_id, withThemesCount: true);

            return ApiResponse::builder()
                ->success()
                ->data([
                    'playground' => $playground,
                ])
                ->json();
        } catch (ModelNotFoundException $e) {
            return ApiResponse::builder()
                ->error(404, 'Playground non trouvé')
                ->json();
        }
    }

    /**
     * Récupérer les thèmes d'un playground via son slug, avec pagination
     */
    public function themesBySlug(Request $request, string $slug): JsonResponse
    {
        try {
            $playground = $this->findPlaygroundForUserBySlug($slug, $request->user()->user_id);

            $paginator = $this->paginatePlaygroundThemes($playground->themes(), $request);

            return ApiResponse::builder()
                ->success()
                ->data([
                    'themes' => $paginator['items'],
                    'pagination' => $paginator['pagination'],
                ])
                ->json();
        } catch (ModelNotFoundException $e) {
            return ApiResponse::builder()
                ->error(404, 'Playground non trouvé')
                ->json();
        }
    }

    /**
     * Trouver un playground pour un utilisateur à partir de son ID
     */
    private function findPlaygroundForUserById(string $playgroundId, string $userId, bool $withThemesCount = false): Playground
    {
        $query = Playground::where('playground_id', $playgroundId)
            ->where('user_id', $userId);

        if ($withThemesCount) {
            $query->withCount(['themes']);
        }

        return $query->firstOrFail();
    }

    /**
     * Trouver un playground pour un utilisateur à partir de son slug
     */
    private function findPlaygroundForUserBySlug(string $slug, string $userId, bool $withThemesCount = false): Playground
    {
        $query = Playground::where('slug', $slug)
            ->where('user_id', $userId);

        if ($withThemesCount) {
            $query->withCount(['themes']);
        }

        return $query->firstOrFail();
    }

    /**
     * Paginer les thèmes d'un playground
     *
     * @param Builder|Relation $themesQuery
     * @param Request $request
     * @return array{items: array, pagination: array}
     */
    private function paginatePlaygroundThemes($themesQuery, Request $request): array
    {
        // Normalisation des paramètres de pagination
        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        $page = (int) $request->input('page', 1);
        $page = max(1, $page);

        // Tri par défaut (le front peut se baser sur created_at desc)
        if ($themesQuery instanceof Relation || $themesQuery instanceof Builder) {
            $themesQuery->orderBy('created_at', 'desc');
        }

        return PaginationUtil::paginate($themesQuery, $perPage, $page);
    }
}
