<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Users\Actions\AdminUserActionService;
use App\Domain\Admin\Users\Queries\AdminUserQueryService;
use App\Domain\ThemeMembers\Queries\ThemeMemberQueryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\ListAdminUsersRequest;
use App\Http\Requests\Admin\User\StoreAdminUserRequest;
use App\Http\Requests\User\PatchUserRequest;
use App\Http\Resources\Users\RoleResource;
use App\Http\Resources\Users\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Auth\User;
use App\Support\Pagination\OffsetPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Users
 *
 * Endpoints for reading and mutating user accounts from the authenticated application context.
 */
class AdminUserController extends Controller
{
    /**
     * Initialize the controller with admin user query and command handlers.
     *
     * @param  AdminUserQueryService  $queryService  Service that retrieves admin-visible user datasets.
     * @param  AdminUserActionService  $actionService  Service that applies admin user account mutations.
     */
    public function __construct(
        private readonly AdminUserQueryService $queryService,
        private readonly AdminUserActionService $actionService,
        private readonly ThemeMemberQueryService $themeMemberQueryService,
    ) {}

    /**
     * List admin-visible users with filters, sorting, and pagination metadata.
     *
     * @param  ListAdminUsersRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @apiResourceCollection App\Http\Resources\Docs\Users\AdminUserIndexResponseCollection
     *
     * @apiResourceModel App\Models\Auth\User paginate=15
     *
     * @responseFile 403 resources/docs/responses/errors/forbidden.json
     */
    public function index(ListAdminUsersRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $actor = $request->user();

        if ($actor->role_power < 100) {
            if (empty($validated['theme_id']) || empty($validated['search'])) {
                throw new \Illuminate\Auth\Access\AuthorizationException('Admin or theme-member-search context required');
            }

            $theme = $this->themeMemberQueryService->findTheme((string) $validated['theme_id']);
            $this->authorize('manageMembers', $theme);

            $users = $this->themeMemberQueryService->searchUsers($theme, (string) $validated['search']);

            return ApiResponse::builder()
                ->success()
                ->messageCode('theme.users.search.success')
                ->data(UserResource::collection($users->values())->resolve())
                ->json();
        }

        $this->authorize('viewAny', User::class);

        $pagination = OffsetPagination::extract($validated);
        $result = $this->queryService->paginate($validated, $pagination);
        $extras = $this->queryService->additionalStats();

        /** @var \Illuminate\Pagination\LengthAwarePaginator $users */
        $users = $result['users'];

        return ApiResponse::builder()
            ->success()
            ->data(UserResource::collection($users->items())->resolve())
            ->meta([
                ...OffsetPagination::meta($users),
                'sorting' => [
                    'sort_by' => $result['sort_by'],
                    'sort_direction' => $result['sort_direction'],
                    'available_sort_fields' => $result['allowed_sort_fields'],
                ],
                'filters' => $result['filters'],
                'roles' => RoleResource::collection($extras['roles'])->resolve(),
                'stats' => $extras['stats'],
            ])
            ->json();
    }

    /**
     * Create a new user account from the admin panel.
     *
     * @param  StoreAdminUserRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @responseFile 201 resources/docs/responses/success.json {"message_code":"user.create.success","data":{"user_id":"2a7188b7-8fd0-4bb9-9f9c-e61c3f4f7b24","username":"john","email":"john@example.com"}}
     * @responseFile 422 resources/docs/responses/errors/validation-invalid.json
     */
    public function store(StoreAdminUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = $this->actionService->create($request->validated(), $request->file('avatar'));

        return ApiResponse::builder()
            ->success(201)
            ->messageCode('user.create.success')
            ->data(UserResource::make($user)->resolve())
            ->json();
    }

    /**
     * Return detailed admin data for one user account.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  User  $user  User account resolved from the route and targeted by this action.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @apiResource App\Http\Resources\Docs\Users\AdminUserShowResponseResource
     *
     * @apiResourceModel App\Models\Auth\User
     *
     * @responseFile 404 resources/docs/responses/errors/not-found.json
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $data = $this->queryService->details($user);

        return ApiResponse::builder()
            ->success()
            ->messageCode('user.show.success')
            ->data([
                'user' => UserResource::make($data['user'])->resolve(),
                'additional_stats' => $data['additional_stats'],
            ])
            ->json();
    }

    /**
     * Update one user account through the unified users PATCH endpoint.
     *
     * @param  PatchUserRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  User  $user  User account resolved from the route and targeted by this action.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @responseFile 200 resources/docs/responses/success.json {"message_code":"user.update.success","data":{"user_id":"2a7188b7-8fd0-4bb9-9f9c-e61c3f4f7b24","username":"johnny","email":"johnny@example.com"}}
     * @responseFile 422 resources/docs/responses/errors/validation-invalid.json
     */
    public function update(PatchUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $result = $this->actionService->update($request->user(), $user, $request->validated(), $request->file('avatar'));

        if ($result['email_changed']) {
            return ApiResponse::builder()
                ->success()
                ->messageCode('user.update.email')
                ->data(UserResource::make($result['user'])->resolve())
                ->json();
        }

        return ApiResponse::builder()
            ->success()
            ->messageCode('user.update.success')
            ->data(UserResource::make($result['user'])->resolve())
            ->json();
    }

    /**
     * Permanently delete one user account from the admin panel.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  User  $user  User account resolved from the route and targeted by this action.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @responseFile 200 resources/docs/responses/success.json {"message_code":"user.delete.success"}
     * @responseFile 404 resources/docs/responses/errors/not-found.json
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $this->actionService->delete($user, $request->user());

        return ApiResponse::builder()
            ->success()
            ->messageCode('user.delete.success')
            ->json();
    }
}
