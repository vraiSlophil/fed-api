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

/**
 * @group Tasks
 *
 * Endpoints for listing, creating, updating, and deleting tasks visible to the user.
 */
class TaskController extends Controller
{
    /**
     * Initialize the controller with task query and command handlers.
     *
     * @param  TaskQueryService  $queryService  Service that loads task lists and visibility-scoped resources.
     * @param  TaskActionService  $actionService  Service that applies task lifecycle state changes.
     */
    public function __construct(
        private readonly TaskQueryService $queryService,
        private readonly TaskActionService $actionService,
    ) {}

    /**
     * List tasks visible to the authenticated user.
     *
     * @param  ListTaskRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @response 200 {
     *   "status": "success",
     *   "message": "Ok",
     *   "message_code": "task.list",
     *   "data": [
     *     {
     *       "task_id": "4fa8bbeb-cbe6-4628-b07f-bf07df6fbc0f",
     *       "theme_id": "278fdd58-2050-4556-9393-8195d1a4ed74",
     *       "title": "Prepare release notes",
     *       "status": "in_progress",
     *       "archived_at": null,
     *       "validated_at": null,
     *       "created_at": "2026-03-10T10:00:00+00:00",
     *       "updated_at": "2026-03-10T11:00:00+00:00"
     *     }
     *   ],
     *   "meta": {
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 42,
     *     "last_page": 3,
     *     "from": 1,
     *     "to": 15,
     *     "has_next": true
     *   }
     * }
     *
     * @responseFile 422 resources/docs/responses/errors/validation-invalid.json
     */
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

    /**
     * Create a new task in the requested theme.
     *
     * @param  StoreTaskRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @response 201 {
     *   "status": "success",
     *   "message": "Created",
     *   "message_code": "task.created",
     *   "data": {
     *     "task": {
     *       "task_id": "4fa8bbeb-cbe6-4628-b07f-bf07df6fbc0f",
     *       "theme_id": "278fdd58-2050-4556-9393-8195d1a4ed74",
     *       "title": "Prepare release notes",
     *       "status": "todo",
     *       "archived_at": null,
     *       "validated_at": null,
     *       "created_at": "2026-03-10T10:00:00+00:00",
     *       "updated_at": "2026-03-10T10:00:00+00:00"
     *     }
     *   }
     * }
     *
     * @responseFile 422 resources/docs/responses/errors/validation-invalid.json
     */
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

    /**
     * Return one task visible to the authenticated user.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Task  $task  Task instance being read or mutated by this method.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @responseFile 200 resources/docs/responses/success.json {"message_code":"task.show","data":{"task":{"task_id":"4fa8bbeb-cbe6-4628-b07f-bf07df6fbc0f","title":"Prepare release notes","status":"in_progress"}}}
     * @responseFile 404 resources/docs/responses/errors/not-found.json
     */
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

    /**
     * Update an existing task visible to the authenticated user.
     *
     * @param  UpdateTaskRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Task  $task  Task instance being read or mutated by this method.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @responseFile 200 resources/docs/responses/success.json {"message_code":"task.updated","data":{"task":{"task_id":"4fa8bbeb-cbe6-4628-b07f-bf07df6fbc0f","title":"Prepare release notes","status":"done"}}}
     * @responseFile 404 resources/docs/responses/errors/not-found.json
     */
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

    /**
     * Delete a task visible to the authenticated user.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Task  $task  Task instance being read or mutated by this method.
     * @return Response HTTP response generated by the method.
     *
     * This endpoint returns `204 No Content` and therefore does not include the standard JSON envelope.
     *
     * @response 204 scenario="No Content"
     *
     * @responseFile 404 resources/docs/responses/errors/not-found.json
     */
    public function destroy(Request $request, Task $task): Response
    {
        $this->authorize('delete', $task);
        $this->actionService->delete($request->user(), $task);

        return ApiResponse::noContent();
    }
}
