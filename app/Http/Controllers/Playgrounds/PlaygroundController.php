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
    public function __construct(
        private readonly PlaygroundQueryService $queryService,
        private readonly PlaygroundActionService $actionService,
    ) {}

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

    public function destroy(Request $request, Playground $playground): JsonResponse
    {
        $this->authorize('delete', $playground);

        $this->actionService->delete($request->user(), $playground->playground_id);

        return ApiResponse::builder()
            ->success()
            ->messageCode('playground.delete.success')
            ->json();
    }

    public function setAsDefault(Request $request, Playground $playground): JsonResponse
    {
        $this->authorize('setDefault', $playground);

        $playground = $this->actionService->setAsDefault($request->user(), $playground);

        return ApiResponse::builder()
            ->success()
            ->messageCode('playground.set_default.success')
            ->data([
                'playground' => $playground,
            ])
            ->json();
    }

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
