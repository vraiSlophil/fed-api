<?php

namespace App\Http\Controllers\Playgrounds;

use App\Domain\Playgrounds\Actions\PlaygroundActionService;
use App\Domain\Playgrounds\Queries\PlaygroundQueryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Playground\ListPlaygroundThemesRequest;
use App\Http\Requests\Playground\StorePlaygroundRequest;
use App\Http\Requests\Playground\UpdatePlaygroundRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Playgrounds\Playground;
use App\Support\Pagination\OffsetPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaygroundController extends Controller
{
    /**
     * Initialize the controller with playground query and command handlers.
     *
     * @param  PlaygroundQueryService  $queryService  Service that reads playground resources and derived stats.
     * @param  PlaygroundActionService  $actionService  Service that creates, updates, and deletes playgrounds.
     */
    public function __construct(
        private readonly PlaygroundQueryService $queryService,
        private readonly PlaygroundActionService $actionService,
    ) {}

    /**
     * List playgrounds owned by the authenticated user.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function index(Request $request): JsonResponse
    {
        $playgrounds = $this->queryService->listForUser($request->user());

        return ApiResponse::builder()
            ->success()
            ->messageCode('playground.list.success')
            ->data([
                'playgrounds' => $playgrounds,
            ])
            ->json();
    }

    /**
     * Return one playground owned by the authenticated user.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Playground  $playground  Playground targeted by the operation.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function show(Request $request, Playground $playground): JsonResponse
    {
        $this->authorize('view', $playground);

        $playground = $this->queryService->findForUserById($request->user(), $playground->playground_id, withThemesCount: true);

        return ApiResponse::builder()
            ->success()
            ->messageCode('playground.show.success')
            ->data([
                'playground' => $playground,
            ])
            ->json();
    }

    /**
     * Create a playground for the authenticated user.
     *
     * @param  StorePlaygroundRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function store(StorePlaygroundRequest $request): JsonResponse
    {
        $playground = $this->actionService->create($request->user(), $request->validated());

        return ApiResponse::builder()
            ->success(201)
            ->messageCode('playground.create.success')
            ->data([
                'playground' => $playground,
            ])
            ->json();
    }

    /**
     * Update a playground owned by the authenticated user.
     *
     * @param  UpdatePlaygroundRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Playground  $playground  Playground targeted by the operation.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function update(UpdatePlaygroundRequest $request, Playground $playground): JsonResponse
    {
        $this->authorize('update', $playground);

        $playground = $this->actionService->update($playground, $request->validated());

        return ApiResponse::builder()
            ->success()
            ->messageCode('playground.update.success')
            ->data([
                'playground' => $playground,
            ])
            ->json();
    }

    /**
     * Delete a playground owned by the authenticated user.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Playground  $playground  Playground targeted by the operation.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function destroy(Request $request, Playground $playground): JsonResponse
    {
        $this->authorize('delete', $playground);

        $this->actionService->delete($request->user(), $playground->playground_id);

        return ApiResponse::builder()
            ->success()
            ->messageCode('playground.delete.success')
            ->json();
    }

    /**
     * Return aggregated statistics for the specified playground.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Playground  $playground  Playground targeted by the operation.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function stats(Request $request, Playground $playground): JsonResponse
    {
        $this->authorize('stats', $playground);

        $stats = $this->queryService->statsFor($playground);

        return ApiResponse::builder()
            ->success()
            ->messageCode('playground.stats.success')
            ->data([
                'playground' => $playground,
                'stats' => $stats,
            ])
            ->json();
    }

    /**
     * List themes accessible in the specified playground.
     *
     * @param  ListPlaygroundThemesRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Playground  $playground  Playground targeted by the operation.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function themes(ListPlaygroundThemesRequest $request, Playground $playground): JsonResponse
    {
        $this->authorize('view', $playground);

        $validated = $request->validated();
        $pagination = OffsetPagination::extract($validated);
        $paginator = $this->queryService->paginateAccessibleThemes($request->user(), $playground, $pagination);

        return ApiResponse::builder()
            ->success()
            ->messageCode('playground.themes.list.success')
            ->data($paginator->items())
            ->meta(OffsetPagination::meta($paginator))
            ->json();
    }

    /**
     * Return one playground by slug for the authenticated user.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Playground  $playground  Playground targeted by the operation.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function showBySlug(Request $request, Playground $playground): JsonResponse
    {
        $this->authorize('view', $playground);

        return ApiResponse::builder()
            ->success()
            ->messageCode('playground.show.success')
            ->data([
                'playground' => $playground->loadCount('themes'),
            ])
            ->json();
    }

    /**
     * List accessible themes for a playground resolved by slug.
     *
     * @param  ListPlaygroundThemesRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Playground  $playground  Playground targeted by the operation.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function themesBySlug(ListPlaygroundThemesRequest $request, Playground $playground): JsonResponse
    {
        $this->authorize('view', $playground);

        $validated = $request->validated();
        $pagination = OffsetPagination::extract($validated);
        $paginator = $this->queryService->paginateAccessibleThemes($request->user(), $playground, $pagination);

        return ApiResponse::builder()
            ->success()
            ->messageCode('playground.themes.list.success')
            ->data($paginator->items())
            ->meta(OffsetPagination::meta($paginator))
            ->json();
    }
}
