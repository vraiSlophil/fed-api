<?php

namespace App\Http\Controllers\Themes;

use App\Domain\Themes\Actions\ThemeActionService;
use App\Domain\Themes\Queries\ThemeQueryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Theme\ListThemeRequest;
use App\Http\Requests\Theme\StoreThemeRequest;
use App\Http\Requests\Theme\UpdateThemeRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Themes\Theme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Theme endpoints for authenticated users.
 *
 * @group Themes
 */
class ThemeController extends Controller
{
    /**
     * Initialize the controller with theme query and command handlers.
     *
     * @param  ThemeQueryService  $queryService  Service that reads themes visible to the current user.
     * @param  ThemeActionService  $actionService  Service that creates, updates, and deletes themes.
     */
    public function __construct(
        private readonly ThemeQueryService $queryService,
        private readonly ThemeActionService $actionService,
    ) {}

    /**
     * List themes owned by the user and themes shared with the user.
     *
     * @param  ListThemeRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @return JsonResponse JSON API response using the standard envelope.
     */
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

    /**
     * Create a new theme in a playground owned by the user.
     *
     * @param  StoreThemeRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @response 201 {
     *   "status": "success",
     *   "message": "Ok",
     *   "message_code": "theme.create.success",
     *   "data": {
     *     "theme": {
     *       "theme_id": "278fdd58-2050-4556-9393-8195d1a4ed74",
     *       "playground_id": "5e4f4aa4-a102-4878-8b86-9623a02f2f01",
     *       "title": "Roadmap",
     *       "color": "#2563EB",
     *       "owner_id": "2a7188b7-8fd0-4bb9-9f9c-e61c3f4f7b24",
     *       "created_at": "2026-03-10T10:00:00+00:00",
     *       "updated_at": "2026-03-10T10:00:00+00:00"
     *     }
     *   }
     * }
     */
    public function store(StoreThemeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->queryService->assertOwnedPlaygroundExists($request->user(), (string) $validated['playground_id']);

        $theme = $this->actionService->create($request->user(), $validated);

        return ApiResponse::builder()
            ->success(201)
            ->messageCode('theme.create.success')
            ->data([
                'theme' => $theme,
            ])
            ->json();
    }

    /**
     * Return one theme visible to the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @return JsonResponse JSON API response using the standard envelope.
     */
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

    /**
     * Update one theme visible to the authenticated user.
     *
     * @param  UpdateThemeRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function update(UpdateThemeRequest $request, Theme $theme): JsonResponse
    {
        $this->authorize('update', $theme);

        $theme = $this->actionService->update($request->user(), $theme, $request->validated());

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

    /**
     * Delete one theme owned by the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @return Response HTTP response generated by the method.
     */
    public function destroy(\Illuminate\Http\Request $request, Theme $theme): Response
    {
        $this->authorize('delete', $theme);

        $this->actionService->delete($theme);

        return ApiResponse::noContent();
    }
}
