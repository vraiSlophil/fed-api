<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Task;
use App\Models\Theme;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    /**
     * Afficher une liste des tâches.
     *
     * Filtres disponibles:
     * - theme_id: filtrer par thème
     * - status: filtrer par statut (todo, doing, done)
     * - archived: filtrer les tâches archivées (true) ou non archivées (false)
     * - validated: filtrer les tâches validées (true) ou non validées (false)
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->user_id;

        // Construire la requête de base pour toutes les tâches accessibles à l'utilisateur
        $query = $this->buildTasksQueryForUser($userId, $request);

        // Appliquer les filtres et les tris
        $query = $this->applyFiltersAndSorting($query, $request);

        // Recherche par titre (insensible aux accents et à la casse)
        if ($request->has('search') && ! empty($request->search)) {
            return $this->handleSearchRequest($query, $request);
        }

        // Pagination des résultats
        $perPage = $request->has('per_page') ? intval($request->per_page) : 15;
        $tasks = $query->paginate($perPage);

        return ApiResponse::builder()
            ->success()
            ->data([
                'tasks' => $tasks->items(),
                'pagination' => [
                    'total' => $tasks->total(),
                    'per_page' => $tasks->perPage(),
                    'current_page' => $tasks->currentPage(),
                    'last_page' => $tasks->lastPage(),
                    'from' => $tasks->firstItem(),
                    'to' => $tasks->lastItem(),
                ],
            ])
            ->json();
    }

    /**
     * Construit la requête de base pour récupérer les tâches accessibles à l'utilisateur
     */
    private function buildTasksQueryForUser(string $userId, Request $request): Builder
    {
        // Récupérer les tâches dont l'utilisateur est le propriétaire
        $query = Task::where(function ($query) use ($userId) {
            // Tâches créées par l'utilisateur
            $query->where('user_id', $userId);

            // OU tâches des thèmes où l'utilisateur est invité avec permission de voir
            $query->orWhereHas('theme.themeUserPermissions', function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->where('can_view', true)
                    ->where('status', 'active');
            });
        });

        // Filtrer par thème si spécifié
        if ($request->has('theme_id')) {
            $query->where('theme_id', $request->theme_id);

            // Vérifier que l'utilisateur a accès à ce thème
            $theme = Theme::where('theme_id', $request->theme_id)
                ->where(function ($q) use ($userId) {
                    $q->where('owner_id', $userId)
                        ->orWhereHas('themeUserPermissions', function ($subq) use ($userId) {
                            $subq->where('user_id', $userId)
                                ->where('can_view', true)
                                ->where('status', 'active');
                        });
                })
                ->firstOrFail();
        }

        return $query;
    }

    /**
     * Applique les filtres et le tri à la requête
     */
    private function applyFiltersAndSorting(Builder $query, Request $request): Builder
    {
        // Filtrer par statut si spécifié
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filtrer par statut d'archivage
        if ($request->has('archived')) {
            if (filter_var($request->archived, FILTER_VALIDATE_BOOLEAN)) {
                // Tâches archivées
                $query->whereNotNull('archived_at');
            } else {
                // Tâches non archivées
                $query->whereNull('archived_at');
            }
        } else {
            // Par défaut, on n'affiche que les tâches non archivées
            $query->whereNull('archived_at');
        }

        // Filtrer par validation
        if ($request->has('validated')) {
            if (filter_var($request->validated, FILTER_VALIDATE_BOOLEAN)) {
                // Tâches validées
                $query->whereNotNull('validated_at');
            } else {
                // Tâches non validées
                $query->whereNull('validated_at');
            }
        }

        // Filtrage multiple par statut
        if ($request->has('statuses')) {
            $statuses = explode(',', $request->statuses);
            $query->whereIn('status', $statuses);
        }

        // Tri par date de création
        if ($request->has('sort')) {
            $direction = $request->sort === 'asc' ? 'asc' : 'desc';
            $query->orderBy('created_at', $direction);
        } else {
            // Par défaut, trier par date de création décroissante (plus récent en premier)
            $query->orderBy('created_at', 'desc');
        }

        return $query;
    }

    /**
     * Gère la recherche par titre
     */
    private function handleSearchRequest(Builder $query, Request $request): JsonResponse
    {
        $searchTerm = $this->normalizeString($request->search);

        // Version alternative: utiliser une méthode basée sur le php pour la recherche
        $tasks = $query->get();

        // Filtrer les résultats en PHP
        $filteredTasks = $tasks->filter(function ($task) use ($searchTerm) {
            $normalizedTitle = $this->normalizeString($task->title);

            return strpos($normalizedTitle, $searchTerm) !== false;
        });

        // Paginer manuellement les résultats
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);
        $paginatedTasks = $this->paginateCollection($filteredTasks, $perPage, $page);

        return ApiResponse::builder()
            ->success()
            ->data([
                'tasks' => $paginatedTasks['items'],
                'pagination' => [
                    'total' => $paginatedTasks['total'],
                    'per_page' => $paginatedTasks['per_page'],
                    'current_page' => $paginatedTasks['current_page'],
                    'last_page' => $paginatedTasks['last_page'],
                    'from' => $paginatedTasks['from'],
                    'to' => $paginatedTasks['to'],
                ],
            ])
            ->json();
    }

    /**
     * Normalise une chaîne en retirant les accents et en la convertissant en minuscules
     */
    private function normalizeString(string $string): string
    {
        // Convertir en minuscules
        $string = mb_strtolower($string, 'UTF-8');

        // Supprimer les accents (décomposer les caractères accentués puis supprimer les marques diacritiques)
        return transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $string);
    }

    /**
     * Pagination manuelle d'une collection
     */
    private function paginateCollection($collection, $perPage, $page)
    {
        $total = $collection->count();
        $lastPage = ceil($total / $perPage);

        $currentPage = $page <= $lastPage ? $page : 1;
        $startIndex = ($currentPage - 1) * $perPage;

        $items = $collection->slice($startIndex, $perPage)->values()->all();

        return [
            'items' => $items,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'from' => $total > 0 ? $startIndex + 1 : null,
            'to' => $total > 0 ? min($startIndex + $perPage, $total) : null,
        ];
    }

    /**
     * Créer une nouvelle tâche.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme_id' => 'required|uuid|exists:themes,theme_id',
            'title' => 'required|string|max:255',
            'status' => 'sometimes|in:todo,doing,done',
        ]);

        // Vérifier que l'utilisateur a accès au thème
        $theme = Theme::where('theme_id', $validated['theme_id'])
            ->where(function ($query) {
                $query->where('owner_id', Auth::id())
                    ->orWhereHas('themeUserPermissions', function ($q) {
                        $q->where('user_id', Auth::id())
                            ->where('can_add_task', true)
                            ->where('status', 'active');
                    });
            })
            ->firstOrFail();

        // Créer la tâche - le mutateur setStatusAttribute gère automatiquement validated_at
        $task = Task::create([
            'theme_id' => $validated['theme_id'],
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'status' => $validated['status'] ?? 'todo',
        ]);

        return ApiResponse::builder()
            ->success(201)
            ->data([
                'task' => $task,
            ])
            ->json();
    }

    /**
     * Afficher une tâche spécifique.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->user_id;

        $task = Task::where('task_id', $id)
            ->where(function ($query) use ($userId) {
                // Tâche créée par l'utilisateur
                $query->where('user_id', $userId);

                // OU tâche d'un thème où l'utilisateur est invité avec permission de voir
                $query->orWhereHas('theme.themeUserPermissions', function ($q) use ($userId) {
                    $q->where('user_id', $userId)
                        ->where('can_view', true)
                        ->where('status', 'active');
                });
            })
            ->firstOrFail();

        return ApiResponse::builder()
            ->success()
            ->data([
                'task' => $task,
            ])
            ->json();
    }

    /**
     * Mettre à jour une tâche existante.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $task = Task::where('task_id', $id)->firstOrFail();

        // Vérifier si l'utilisateur peut modifier cette tâche
        $userId = $request->user()->user_id;
        $theme = $task->theme;

        if (! $theme->canEditTaskBy($userId)) {
            return ApiResponse::builder()
                ->error(403, 'Vous n\'avez pas la permission de modifier cette tâche.')
                ->json();
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'status' => 'sometimes|required|in:todo,doing,done',
        ]);

        // Vérifier spécifiquement la permission de validation si le statut est modifié en "done"
        if (isset($validated['status']) && $validated['status'] === 'done' && $task->status !== 'done') {
            if (! $theme->canValidateTaskBy($userId)) {
                return ApiResponse::builder()
                    ->error(403, 'Vous n\'avez pas la permission de valider cette tâche.')
                    ->json();
            }
        }

        // Mise à jour - le mutateur setStatusAttribute gère automatiquement validated_at
        $task->update($validated);

        return ApiResponse::builder()
            ->success()
            ->data([
                'task' => $task,
            ])
            ->json();
    }

    /**
     * Archiver une tâche.
     */
    public function archive(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->user_id;
        $task = Task::where('task_id', $id)
            ->whereNull('archived_at')
            ->firstOrFail();

        // Vérifier que l'utilisateur a le droit de modifier cette tâche
        $theme = $task->theme;
        if (! $theme->canEditTaskBy($userId)) {
            return ApiResponse::builder()
                ->error(403, 'Vous n\'avez pas la permission de modifier cette tâche.')
                ->json();
        }

        $task->archived_at = now();
        $task->save();

        return ApiResponse::builder()
            ->success()
            ->data([
                'task' => $task,
            ])
            ->json();
    }

    /**
     * Restaurer une tâche archivée.
     */
    public function restore(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->user_id;
        $task = Task::where('task_id', $id)
            ->whereNotNull('archived_at')
            ->firstOrFail();

        // Vérifier que l'utilisateur a le droit de modifier cette tâche
        $theme = $task->theme;
        if (! $theme->canEditTaskBy($userId)) {
            return ApiResponse::builder()
                ->error(403, 'Vous n\'avez pas la permission de modifier cette tâche.')
                ->json();
        }

        $task->archived_at = null;
        $task->save();

        return ApiResponse::builder()
            ->success()
            ->data([
                'task' => $task,
            ])
            ->json();
    }

    /**
     * Marquer une tâche comme terminée.
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        $task = Task::where('task_id', $id)->firstOrFail();

        // Vérifier si l'utilisateur peut valider cette tâche
        $userId = $request->user()->user_id;
        $theme = $task->theme;

        if (! $theme->canValidateTaskBy($userId)) {
            return ApiResponse::builder()
                ->error(403, 'Vous n\'avez pas la permission de valider cette tâche.')
                ->json();
        }

        // Si l'utilisateur est autorisé, mettre à jour la tâche
        $task->status = 'done';
        $task->save();

        return ApiResponse::builder()
            ->success()
            ->data([
                'task' => $task,
            ])
            ->json();
    }

    /**
     * Marquer une tâche comme non terminée.
     */
    public function uncomplete(Request $request, string $id): JsonResponse
    {
        $task = Task::where('task_id', $id)->firstOrFail();

        // Vérifier si l'utilisateur peut valider cette tâche
        $userId = $request->user()->user_id;
        $theme = $task->theme;

        if (! $theme->canValidateTaskBy($userId)) {
            return ApiResponse::builder()
                ->error(403, 'Vous n\'avez pas la permission de modifier la validation de cette tâche.')
                ->json();
        }

        // Si l'utilisateur est autorisé, mettre à jour la tâche
        $task->status = 'todo';
        $task->save();

        return ApiResponse::builder()
            ->success()
            ->data([
                'task' => $task,
            ])
            ->json();
    }

    /**
     * Supprimer une tâche.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->user_id;
        $task = Task::where('task_id', $id)->firstOrFail();
        $theme = $task->theme;

        // Vérifier si l'utilisateur a le droit de supprimer cette tâche
        if (! $theme->canDeleteTaskBy($userId)) {
            return ApiResponse::builder()
                ->error(403, 'Vous n\'avez pas la permission de supprimer cette tâche.')
                ->json();
        }

        $task->delete();

        return ApiResponse::builder()
            ->success(204)
            ->json();
    }
}
