<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Theme;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Responses\ApiResponse;
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
        $query = Task::where('user_id', Auth::id());

        // Filtrer par thème si spécifié
        if ($request->has('theme_id')) {
            $query->where('theme_id', $request->theme_id);
        }

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

        // Recherche par titre (insensible aux accents et à la casse)
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $this->normalizeString($request->search);

            // Version alternative: utiliser une méthode basée sur le php pour la recherche
            $tasks = $query->get();

            // Filtrer les résultats en PHP
            $filteredTasks = $tasks->filter(function($task) use ($searchTerm) {
                $normalizedTitle = $this->normalizeString($task->title);
                return strpos($normalizedTitle, $searchTerm) !== false;
            });

            // Paginer manuellement les résultats
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 15);
            $paginatedTasks = $this->paginateCollection($filteredTasks, $perPage, $page);

            return ApiResponse::success([
                'tasks' => $paginatedTasks['items'],
                'pagination' => [
                    'total' => $paginatedTasks['total'],
                    'per_page' => $paginatedTasks['per_page'],
                    'current_page' => $paginatedTasks['current_page'],
                    'last_page' => $paginatedTasks['last_page'],
                    'from' => $paginatedTasks['from'],
                    'to' => $paginatedTasks['to'],
                ]
            ]);
        }

        // Pagination des résultats
        $perPage = $request->has('per_page') ? intval($request->per_page) : 15;
        $tasks = $query->paginate($perPage);

        return ApiResponse::success([
            'tasks' => $tasks->items(),
            'pagination' => [
                'total' => $tasks->total(),
                'per_page' => $tasks->perPage(),
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'from' => $tasks->firstItem(),
                'to' => $tasks->lastItem(),
            ]
        ]);
    }

    /**
     * Normalise une chaîne en retirant les accents et en la convertissant en minuscules
     */
    private function normalizeString(string $string): string
    {
        // Convertir en minuscules
        $string = mb_strtolower($string, 'UTF-8');

        // Supprimer les accents (décomposer les caractères accentués puis supprimer les marques diacritiques)
        $string = transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $string);

        return $string;
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
            ->where(function($query) {
                $query->where('owner_id', Auth::id())
                    ->orWhereHas('themeUserPermissions', function($q) {
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

        return ApiResponse::success([
            'task' => $task
        ], 201);
    }

    /**
     * Afficher une tâche spécifique.
     */
    public function show(string $id): JsonResponse
    {
        $task = Task::where('user_id', Auth::id())
            ->where('task_id', $id)
            ->firstOrFail();

        return ApiResponse::success([
            'task' => $task
        ]);
    }

    /**
     * Mettre à jour une tâche existante.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $task = Task::where('user_id', Auth::id())
            ->where('task_id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'status' => 'sometimes|required|in:todo,doing,done',
        ]);

        // Mise à jour - le mutateur setStatusAttribute gère automatiquement validated_at
        $task->update($validated);

        return ApiResponse::success([
            'task' => $task
        ]);
    }

    /**
     * Archiver une tâche.
     */
    public function archive(string $id): JsonResponse
    {
        $task = Task::where('user_id', Auth::id())
            ->where('task_id', $id)
            ->whereNull('archived_at')
            ->firstOrFail();

        $task->archived_at = now();
        $task->save();

        return ApiResponse::success([
            'task' => $task
        ]);
    }

    /**
     * Restaurer une tâche archivée.
     */
    public function restore(string $id): JsonResponse
    {
        $task = Task::where('user_id', Auth::id())
            ->where('task_id', $id)
            ->whereNotNull('archived_at')
            ->firstOrFail();

        $task->archived_at = null;
        $task->save();

        return ApiResponse::success([
            'task' => $task
        ]);
    }

    /**
     * Marquer une tâche comme terminée.
     */
    public function complete(string $id): JsonResponse
    {
        $task = Task::where('user_id', Auth::id())
            ->where('task_id', $id)
            ->firstOrFail();

        // Utiliser la méthode validate du modèle
        $task->validate()->save();

        return ApiResponse::success([
            'task' => $task
        ]);
    }

    /**
     * Marquer une tâche comme non terminée.
     */
    public function uncomplete(string $id): JsonResponse
    {
        $task = Task::where('user_id', Auth::id())
            ->where('task_id', $id)
            ->firstOrFail();

        // Utiliser la méthode invalidate du modèle et changer le statut
        $task->invalidate();
        $task->status = 'doing';
        $task->save();

        return ApiResponse::success([
            'task' => $task
        ]);
    }

    /**
     * Supprimer une tâche.
     */
    public function destroy(string $id): JsonResponse
    {
        $task = Task::where('user_id', Auth::id())
            ->where('task_id', $id)
            ->firstOrFail();

        // Vérifier si l'utilisateur a le droit de supprimer cette tâche
        // Si ce n'est pas le créateur, vérifier les permissions
        if ($task->theme->owner_id !== Auth::id()) {
            $permission = $task->theme->themeUserPermissions()
                ->where('user_id', Auth::id())
                ->where('can_delete_task', true)
                ->where('status', 'active')
                ->firstOrFail();
        }

        $task->delete();

        return ApiResponse::success(null, 204);
    }
}
