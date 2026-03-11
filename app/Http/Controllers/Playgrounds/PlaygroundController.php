<?php

namespace App\Http\Controllers\Playgrounds;

use App\Domain\Playgrounds\Actions\PlaygroundActionService;
use App\Domain\Playgrounds\Queries\PlaygroundQueryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Playground\ListPlaygroundsRequest;
use App\Http\Requests\Playground\StorePlaygroundRequest;
use App\Http\Requests\Playground\UpdatePlaygroundRequest;
use App\Http\Resources\Playgrounds\PlaygroundResource;
use App\Http\Responses\ApiResponse;
use App\Models\Playgrounds\Playground;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * @group Playgrounds
 *
 * Endpoints for listing, creating, updating, and deleting user playgrounds.
 */
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
     * @param  ListPlaygroundsRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @response 200 {
     *   "status": "success",
     *   "message": "Ok",
     *   "message_code": "playground.list.success",
     *   "data": {
     *     "playgrounds": [
     *       {
     *         "playground_id": "5e4f4aa4-a102-4878-8b86-9623a02f2f01",
     *         "name": "Home",
     *         "slug": "home",
     *         "is_default": true,
     *         "themes_count": 3,
     *         "created_at": "2026-03-01T09:00:00+00:00",
     *         "updated_at": "2026-03-01T09:00:00+00:00"
     *       }
     *     ]
     *   }
     * }
     *
     * @responseFile 403 resources/docs/responses/errors/forbidden.json
     */
    public function index(ListPlaygroundsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $slug = array_key_exists('slug', $validated) ? (string) $validated['slug'] : null;

        if ($slug !== null && $slug !== '') {
            $playground = $this->queryService->findForUserBySlug($request->user(), $slug, withThemesCount: true);

            return ApiResponse::builder()
                ->success()
                ->messageCode('playground.show.success')
                ->data([
                    'playground' => PlaygroundResource::make($playground)->resolve(),
                ])
                ->json();
        }

        $playgrounds = $this->queryService->listForUser($request->user());

        return ApiResponse::builder()
            ->success()
            ->messageCode('playground.list.success')
            ->data([
                'playgrounds' => PlaygroundResource::collection($playgrounds)->resolve(),
            ])
            ->json();
    }

    /**
     * Return one playground owned by the authenticated user.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Playground  $playground  Playground targeted by the operation.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @responseFile 200 resources/docs/responses/success.json {"message_code":"playground.show.success","data":{"playground":{"playground_id":"5e4f4aa4-a102-4878-8b86-9623a02f2f01","name":"Home","slug":"home","is_default":true}}}
     * @responseFile 404 resources/docs/responses/errors/not-found.json
     */
    public function show(Request $request, Playground $playground): JsonResponse
    {
        $this->authorize('view', $playground);

        $playground = $this->queryService->findForUserById($request->user(), $playground->playground_id, withThemesCount: true);

        return ApiResponse::builder()
            ->success()
            ->messageCode('playground.show.success')
            ->data([
                'playground' => PlaygroundResource::make($playground)->resolve(),
            ])
            ->json();
    }

    /**
     * Create a playground for the authenticated user.
     *
     * @param  StorePlaygroundRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @responseFile 201 resources/docs/responses/success.json {"message_code":"playground.create.success","data":{"playground":{"playground_id":"5e4f4aa4-a102-4878-8b86-9623a02f2f01","name":"Home","slug":"home","is_default":true}}}
     * @responseFile 422 resources/docs/responses/errors/validation-invalid.json
     */
    public function store(StorePlaygroundRequest $request): JsonResponse
    {
        $playground = $this->actionService->create($request->user(), $request->validated());

        return ApiResponse::builder()
            ->success(201)
            ->messageCode('playground.create.success')
            ->data([
                'playground' => PlaygroundResource::make($playground)->resolve(),
            ])
            ->json();
    }

    /**
     * Update a playground owned by the authenticated user.
     *
     * @param  UpdatePlaygroundRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Playground  $playground  Playground targeted by the operation.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @responseFile 200 resources/docs/responses/success.json {"message_code":"playground.update.success","data":{"playground":{"playground_id":"5e4f4aa4-a102-4878-8b86-9623a02f2f01","name":"Work","slug":"work","is_default":false}}}
     * @responseFile 404 resources/docs/responses/errors/not-found.json
     */
    public function update(UpdatePlaygroundRequest $request, Playground $playground): JsonResponse
    {
        $this->authorize('update', $playground);

        $playground = $this->actionService->update($playground, $request->validated());

        return ApiResponse::builder()
            ->success()
            ->messageCode('playground.update.success')
            ->data([
                'playground' => PlaygroundResource::make($playground)->resolve(),
            ])
            ->json();
    }

    /**
     * Delete a playground owned by the authenticated user.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Playground  $playground  Playground targeted by the operation.
     * @return Response HTTP response generated by the method.
     *
     * @response 204 scenario="No Content"
     *
     * @responseFile 404 resources/docs/responses/errors/not-found.json
     */
    public function destroy(Request $request, Playground $playground): Response
    {
        $this->authorize('delete', $playground);

        $this->actionService->delete($request->user(), $playground->playground_id);

        return ApiResponse::noContent();
    }
}
