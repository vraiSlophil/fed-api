<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Users\Actions\AdminUserActionService;
use App\Domain\Admin\Users\Queries\AdminUserQueryService;
use App\Domain\ThemeMembers\Queries\ThemeMemberQueryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\ListAdminUsersRequest;
use App\Http\Requests\Admin\User\StoreAdminUserRequest;
use App\Http\Requests\User\PatchUserRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Auth\User;
use App\Support\Pagination\OffsetPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
                ->data([
                    'users' => $users,
                ])
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
            ->data($users->items())
            ->meta([
                ...OffsetPagination::meta($users),
                'sorting' => [
                    'sort_by' => $result['sort_by'],
                    'sort_direction' => $result['sort_direction'],
                    'available_sort_fields' => $result['allowed_sort_fields'],
                ],
                'filters' => $result['filters'],
                ...$extras,
            ])
            ->json();
    }

    /**
     * Create a new user account from the admin panel.
     *
     * @param  StoreAdminUserRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function store(StoreAdminUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = $this->actionService->create($request->validated(), $request->file('avatar'));

        return ApiResponse::builder()
            ->success(201)
            ->messageCode('user.create.success')
            ->data($user)
            ->json();
    }

    /**
     * Return detailed admin data for one user account.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  User  $user  User account resolved from the route and targeted by this action.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $data = $this->queryService->details($user);

        return ApiResponse::builder()
            ->success()
            ->messageCode('user.show.success')
            ->data($data)
            ->json();
    }

    /**
     * Update one user account through the unified users PATCH endpoint.
     *
     * @param  PatchUserRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  User  $user  User account resolved from the route and targeted by this action.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function update(PatchUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $result = $this->actionService->update($request->user(), $user, $request->validated(), $request->file('avatar'));

        if ($result['email_changed']) {
            return ApiResponse::builder()
                ->success()
                ->messageCode('user.update.email')
                ->data($result['user'])
                ->json();
        }

        return ApiResponse::builder()
            ->success()
            ->messageCode('user.update.success')
            ->data($result['user'])
            ->json();
    }

    /**
     * Permanently delete one user account from the admin panel.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  User  $user  User account resolved from the route and targeted by this action.
     * @return JsonResponse JSON API response using the standard envelope.
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
