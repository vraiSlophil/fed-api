<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Users\Actions\AdminUserActionService;
use App\Domain\Admin\Users\Queries\AdminUserQueryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\ListAdminUsersRequest;
use App\Http\Requests\Admin\User\StoreAdminUserRequest;
use App\Http\Requests\Admin\User\UpdateAdminUserRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Auth\User;
use App\Support\Pagination\OffsetPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly AdminUserQueryService $queryService,
        private readonly AdminUserActionService $actionService,
    ) {}

    public function index(ListAdminUsersRequest $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $validated = $request->validated();
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

    public function update(UpdateAdminUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $result = $this->actionService->update($user, $request->validated(), $request->file('avatar'));

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

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $this->actionService->delete($user, $request->user());

        return ApiResponse::builder()
            ->success()
            ->messageCode('user.delete.success')
            ->json();
    }

    public function block(Request $request, User $user): JsonResponse
    {
        $this->authorize('block', $user);

        $user = $this->actionService->block($user, $request->user());

        return ApiResponse::builder()
            ->success(200)
            ->messageCode('user.block.success')
            ->data($user)
            ->json();
    }

    public function unblock(Request $request, User $user): JsonResponse
    {
        $this->authorize('unblock', $user);

        $user = $this->actionService->unblock($user);

        return ApiResponse::builder()
            ->success(200)
            ->messageCode('user.unblock.success')
            ->data($user)
            ->json();
    }
}
