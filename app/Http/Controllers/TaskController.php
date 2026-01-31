<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Task;
use App\Models\Theme;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->user_id;

        $query = $this->buildTasksQueryForUser($userId, $request);

        $query = $this->applyFiltersAndSorting($query, $request);

        if ($request->has('search') && ! empty($request->search)) {
            return $this->handleSearchRequest($query, $request);
        }

        $perPage = $request->has('per_page') ? intval($request->per_page) : 15;
        $tasks = $query->paginate($perPage);

        return ApiResponse::builder()
            ->success()
            ->messageCode('task.list', [])
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

    private function buildTasksQueryForUser(string $userId, Request $request): Builder
    {
        $query = Task::where(function ($query) use ($userId) {
            $query->where('user_id', $userId);

            $query->orWhereHas('theme.themeUserPermissions', function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->where('can_view', true)
                    ->where('status', 'active');
            });
        });

        if ($request->has('theme_id')) {
            $query->where('theme_id', $request->theme_id);

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

    private function applyFiltersAndSorting(Builder $query, Request $request): Builder
    {
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('archived')) {
            if (filter_var($request->archived, FILTER_VALIDATE_BOOLEAN)) {
                $query->whereNotNull('archived_at');
            } else {
                $query->whereNull('archived_at');
            }
        } else {
            $query->whereNull('archived_at');
        }

        if ($request->has('validated')) {
            if (filter_var($request->validated, FILTER_VALIDATE_BOOLEAN)) {
                $query->whereNotNull('validated_at');
            } else {
                $query->whereNull('validated_at');
            }
        }

        if ($request->has('statuses')) {
            $statuses = explode(',', $request->statuses);
            $query->whereIn('status', $statuses);
        }

        if ($request->has('sort')) {
            $direction = $request->sort === 'asc' ? 'asc' : 'desc';
            $query->orderBy('created_at', $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query;
    }

    private function handleSearchRequest(Builder $query, Request $request): JsonResponse
    {
        $searchTerm = $this->normalizeString($request->search);

        $tasks = $query->get();

        $filteredTasks = $tasks->filter(function ($task) use ($searchTerm) {
            $normalizedTitle = $this->normalizeString($task->title);

            return strpos($normalizedTitle, $searchTerm) !== false;
        });

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);
        $paginatedTasks = $this->paginateCollection($filteredTasks, $perPage, $page);

        return ApiResponse::builder()
            ->success()
            ->messageCode('task.search', [])
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

    private function normalizeString(string $string): string
    {
        $string = mb_strtolower($string, 'UTF-8');

        return transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $string);
    }

    private function paginateCollection($collection, $perPage, $page)
    {
        $total = $collection->count();
        $lastPage = (int) ceil($total / $perPage);

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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme_id' => 'required|uuid|exists:themes,theme_id',
            'title' => 'required|string|max:255',
            'status' => 'sometimes|in:todo,doing,done',
        ]);

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

        $task = Task::create([
            'theme_id' => $validated['theme_id'],
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'status' => $validated['status'] ?? 'todo',
        ]);

        return ApiResponse::builder()
            ->success(201, 'Created')
            ->messageCode('task.created', ['task' => $task->task_id])
            ->data([
                'task' => $task,
            ])
            ->json();
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->user_id;

        $task = Task::where('task_id', $id)
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId);
                $query->orWhereHas('theme.themeUserPermissions', function ($q) use ($userId) {
                    $q->where('user_id', $userId)
                        ->where('can_view', true)
                        ->where('status', 'active');
                });
            })
            ->firstOrFail();

        return ApiResponse::builder()
            ->success()
            ->messageCode('task.show', ['task' => $task->task_id])
            ->data([
                'task' => $task,
            ])
            ->json();
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $task = Task::where('task_id', $id)->firstOrFail();

        $userId = $request->user()->user_id;
        $theme = $task->theme;

        if (! $theme->canEditTaskBy($userId)) {
            throw new AuthorizationException('Forbidden');
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'status' => 'sometimes|required|in:todo,doing,done',
        ]);

        if (isset($validated['status']) && $validated['status'] === 'done' && $task->status !== 'done') {
            if (! $theme->canValidateTaskBy($userId)) {
                throw new AuthorizationException('Forbidden');
            }
        }

        $task->update($validated);

        return ApiResponse::builder()
            ->success()
            ->messageCode('task.updated', ['task' => $task->task_id])
            ->data([
                'task' => $task,
            ])
            ->json();
    }

    public function archive(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->user_id;
        $task = Task::where('task_id', $id)
            ->whereNull('archived_at')
            ->firstOrFail();

        $theme = $task->theme;
        if (! $theme->canEditTaskBy($userId)) {
            throw new AuthorizationException('Forbidden');
        }

        $task->archived_at = now();
        $task->save();

        return ApiResponse::builder()
            ->success()
            ->messageCode('task.archived', ['task' => $task->task_id])
            ->data([
                'task' => $task,
            ])
            ->json();
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->user_id;
        $task = Task::where('task_id', $id)
            ->whereNotNull('archived_at')
            ->firstOrFail();

        $theme = $task->theme;
        if (! $theme->canEditTaskBy($userId)) {
            throw new AuthorizationException('Forbidden');
        }

        $task->archived_at = null;
        $task->save();

        return ApiResponse::builder()
            ->success()
            ->messageCode('task.restored', ['task' => $task->task_id])
            ->data([
                'task' => $task,
            ])
            ->json();
    }

    public function complete(Request $request, string $id): JsonResponse
    {
        $task = Task::where('task_id', $id)->firstOrFail();

        $userId = $request->user()->user_id;
        $theme = $task->theme;

        if (! $theme->canValidateTaskBy($userId)) {
            throw new AuthorizationException('Forbidden');
        }

        $task->status = 'done';
        $task->save();

        return ApiResponse::builder()
            ->success()
            ->messageCode('task.completed', ['task' => $task->task_id])
            ->data([
                'task' => $task,
            ])
            ->json();
    }

    public function uncomplete(Request $request, string $id): JsonResponse
    {
        $task = Task::where('task_id', $id)->firstOrFail();

        $userId = $request->user()->user_id;
        $theme = $task->theme;

        if (! $theme->canValidateTaskBy($userId)) {
            throw new AuthorizationException('Forbidden');
        }

        $task->status = 'todo';
        $task->save();

        return ApiResponse::builder()
            ->success()
            ->messageCode('task.uncompleted', ['task' => $task->task_id])
            ->data([
                'task' => $task,
            ])
            ->json();
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->user_id;
        $task = Task::where('task_id', $id)->firstOrFail();
        $theme = $task->theme;

        if (! $theme->canDeleteTaskBy($userId)) {
            throw new AuthorizationException('Forbidden');
        }

        $task->delete();

        return ApiResponse::builder()
            ->success(204, 'No Content')
            ->messageCode('task.deleted', ['task' => $id])
            ->json();
    }
}
