<?php

namespace App\Http\Controllers\Themes;

use App\Domain\Themes\Actions\ThemeActionService;
use App\Domain\Themes\Queries\ThemeQueryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Theme\ListThemeRequest;
use App\Http\Requests\Theme\StoreThemeRequest;
use App\Http\Requests\Theme\UpdateThemeRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Playgrounds\Playground;
use App\Models\Themes\Theme;
use Illuminate\Http\JsonResponse;

class ThemeController extends Controller
{
    public function __construct(
        private readonly ThemeQueryService $queryService,
        private readonly ThemeActionService $actionService,
    ) {}

    public function index(ListThemeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $themes = $this->queryService->listForUser($request->user(), $validated['playground_id'] ?? null);

        return ApiResponse::builder()
            ->success()
            ->messageCode('theme.list.success')
            ->data([
                'themes' => $themes,
            ])
            ->json();
    }

    public function store(StoreThemeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        Playground::query()
            ->where('playground_id', $validated['playground_id'])
            ->where('user_id', $request->user()->user_id)
            ->firstOrFail();

        $theme = $this->actionService->create($request->user(), $validated);

        return ApiResponse::builder()
            ->success(201)
            ->messageCode('theme.create.success')
            ->data([
                'theme' => $theme,
            ])
            ->json();
    }

    public function show(\Illuminate\Http\Request $request, Theme $theme): JsonResponse
    {
        $this->authorize('view', $theme);

        if (! $theme->isOwnedBy($request->user()->user_id)) {
            $theme->permissions = $theme->getPermissionsFor($request->user()->user_id);
        }

        return ApiResponse::builder()
            ->success()
            ->messageCode('theme.show.success')
            ->data([
                'theme' => $theme,
            ])
            ->json();
    }

    public function update(UpdateThemeRequest $request, Theme $theme): JsonResponse
    {
        $this->authorize('update', $theme);

        $theme = $this->actionService->update($theme, $request->validated());

        if (! $theme->isOwnedBy($request->user()->user_id)) {
            $theme->permissions = $theme->getPermissionsFor($request->user()->user_id);
        }

        return ApiResponse::builder()
            ->success()
            ->messageCode('theme.update.success')
            ->data([
                'theme' => $theme,
            ])
            ->json();
    }

    public function destroy(\Illuminate\Http\Request $request, Theme $theme): JsonResponse
    {
        $this->authorize('delete', $theme);

        $this->actionService->delete($theme);

        return ApiResponse::builder()
            ->success(204)
            ->messageCode('theme.delete.success')
            ->json();
    }
}
