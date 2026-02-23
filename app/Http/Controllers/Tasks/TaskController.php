<?php

namespace App\Http\Controllers\Tasks;

use App\Domain\Tasks\Actions\TaskActionService;
use App\Domain\Tasks\Queries\TaskQueryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\ListTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Tasks\Task;
use App\Support\Pagination\OffsetPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TaskController extends Controller
{
    public function __construct(
        private readonly TaskQueryService $queryService,
        private readonly TaskActionService $actionService,
    ) {}

    public function index(ListTaskRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $pagination = OffsetPagination::extract($validated);

        $tasks = $this->queryService->paginateForUser(
            $request->user(),
            $validated,
            $pagination,
        );

        return ApiResponse::builder()
            ->success()
            ->messageCode('task.list', [])
            ->data($tasks->items())
            ->meta(OffsetPagination::meta($tasks))
            ->json();
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $theme = $this->queryService->findThemeForCreation((string) $validated['theme_id']);
        $this->authorize('addTask', $theme);

        $task = $this->actionService->create($request->user(), $theme, $validated);

        return ApiResponse::builder()
            ->success(201, 'Created')
            ->messageCode('task.created', ['task' => $task->task_id])
            ->data([
                'task' => $task,
            ])
            ->json();
    }

    public function show(
        Request $request,
        Task $task
    ): JsonResponse {
        $this->authorize('view', $task);

        return ApiResponse::builder()
            ->success()
            ->messageCode('task.show', ['task' => $task->task_id])
            ->data([
                'task' => $task,
            ])
            ->json();
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $task = $this->actionService->update($request->user(), $task, $request->validated());

        return ApiResponse::builder()
            ->success()
            ->messageCode('task.updated', ['task' => $task->task_id])
            ->data([
                'task' => $task,
            ])
            ->json();
    }

    public function archive(Request $request, Task $task): JsonResponse
    {
        $this->authorize('archive', $task);
        $task = $this->actionService->archive($request->user(), $task);

        return ApiResponse::builder()
            ->success()
            ->messageCode('task.archived', ['task' => $task->task_id])
            ->data([
                'task' => $task,
            ])
            ->json();
    }

    public function restore(Request $request, Task $task): JsonResponse
    {
        $this->authorize('restore', $task);
        $task = $this->actionService->restore($request->user(), $task);

        return ApiResponse::builder()
            ->success()
            ->messageCode('task.restored', ['task' => $task->task_id])
            ->data([
                'task' => $task,
            ])
            ->json();
    }

    public function complete(Request $request, Task $task): JsonResponse
    {
        $this->authorize('validate', $task);
        $task = $this->actionService->complete($request->user(), $task);

        return ApiResponse::builder()
            ->success()
            ->messageCode('task.completed', ['task' => $task->task_id])
            ->data([
                'task' => $task,
            ])
            ->json();
    }

    public function uncomplete(Request $request, Task $task): JsonResponse
    {
        $this->authorize('validate', $task);
        $task = $this->actionService->uncomplete($request->user(), $task);

        return ApiResponse::builder()
            ->success()
            ->messageCode('task.uncompleted', ['task' => $task->task_id])
            ->data([
                'task' => $task,
            ])
            ->json();
    }

    public function destroy(Request $request, Task $task): Response
    {
        $this->authorize('delete', $task);
        $this->actionService->delete($request->user(), $task);

        return ApiResponse::noContent();
    }
}
