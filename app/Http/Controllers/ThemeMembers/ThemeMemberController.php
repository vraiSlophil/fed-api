<?php

namespace App\Http\Controllers\ThemeMembers;

use App\Domain\ThemeMembers\Actions\ThemeMemberActionService;
use App\Domain\ThemeMembers\Queries\ThemeMemberQueryService;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ThemeMember\UpdateThemeMemberPermissionsRequest;
use App\Http\Resources\Themes\ThemeMemberResource;
use App\Http\Resources\Themes\ThemePermissionFlagsResource;
use App\Http\Resources\Themes\ThemePermissionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Themes\Theme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Themes
 *
 * Endpoints for reading and mutating theme members and permissions.
 */
class ThemeMemberController extends Controller
{
    /**
     * Initialize the controller with theme membership query and command handlers.
     *
     * @param  ThemeMemberQueryService  $queryService  Service that reads membership and invitation candidates.
     * @param  ThemeMemberActionService  $actionService  Service that manages invitations and member permissions.
     */
    public function __construct(
        private readonly ThemeMemberQueryService $queryService,
        private readonly ThemeMemberActionService $actionService,
    ) {}

    /**
     * List owner, active members, and pending invites for a theme.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @responseFile 200 resources/docs/responses/success.json {"message_code":"theme.members.list.success","data":[]}
     * @responseFile 403 resources/docs/responses/errors/forbidden.json
     */
    public function listMembers(Request $request, Theme $theme): JsonResponse
    {
        $this->authorize('manageMembers', $theme);

        $members = $this->queryService->listMembers($theme);

        return ApiResponse::builder()
            ->success()
            ->messageCode('theme.members.list.success')
            ->data(ThemeMemberResource::collection($members)->resolve())
            ->json();
    }

    /**
     * Update permission flags for the specified theme member.
     *
     * @param  UpdateThemeMemberPermissionsRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @param  string  $userId  Identifier of the user.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @responseFile 200 resources/docs/responses/success.json {"message_code":"theme.member.permissions.updated","data":{"permissions":{"can_view":true,"can_update_theme":false,"can_add_task":true,"can_edit_task":true,"can_delete_task":false,"can_validate_task":false},"status":"active","target_playground_id":"5e4f4aa4-a102-4878-8b86-9623a02f2f01"}}
     * @responseFile 403 resources/docs/responses/errors/forbidden.json
     */
    public function updateMemberPermissions(
        UpdateThemeMemberPermissionsRequest $request,
        Theme $theme,
        string $userId,
    ): JsonResponse {
        $validated = $request->validated();
        $actor = $request->user();
        $isSelfUpdate = $userId === $actor->user_id;

        if (array_key_exists('target_playground_id', $validated)) {
            if (! $isSelfUpdate) {
                throw new ApiException('permission.denied', [], 403, 'Only the current member can change target playground');
            }

            foreach ([
                'can_view',
                'can_update_theme',
                'can_add_task',
                'can_edit_task',
                'can_delete_task',
                'can_validate_task',
                'status',
            ] as $restrictedField) {
                if (array_key_exists($restrictedField, $validated)) {
                    throw new ApiException('permission.denied', [], 403, 'Cannot update member permissions while moving playground');
                }
            }

            $this->authorize('view', $theme);

            $permission = $this->actionService->moveToPlayground($actor, $theme, $validated);

            return ApiResponse::builder()
                ->success(200)
                ->messageCode('theme.move.success', ['target_playground_id' => $validated['target_playground_id']])
                ->data(ThemePermissionResource::make($permission)->resolve())
                ->json();
        } else {
            $this->authorize('manageMembers', $theme);
        }

        $permission = $this->actionService->updateMemberPermissions($theme, $userId, $validated);

        return ApiResponse::builder()
            ->success(200)
            ->messageCode('theme.member.permissions.updated', ['user_id' => $userId])
            ->data([
                'permissions' => ThemePermissionFlagsResource::make($permission)->resolve(),
                'status' => $permission->status,
                'target_playground_id' => $permission->target_playground_id,
            ])
            ->json();
    }

    /**
     * Remove a member from the theme permission list.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @param  string  $userId  Identifier of the user.
     * @return JsonResponse JSON API response using the standard envelope.
     *
     * @responseFile 200 resources/docs/responses/success.json {"message_code":"theme.member.removed"}
     * @responseFile 403 resources/docs/responses/errors/forbidden.json
     */
    public function removeMember(Request $request, Theme $theme, string $userId): JsonResponse
    {
        if ($userId === $request->user()->user_id) {
            $this->authorize('view', $theme);

            $this->actionService->leaveTheme($request->user(), $theme);

            return ApiResponse::builder()
                ->success(200)
                ->messageCode('theme.member.left', ['user_id' => $userId])
                ->json();
        }

        $this->authorize('manageMembers', $theme);

        $this->actionService->removeMember($theme, $userId);

        return ApiResponse::builder()
            ->success(200)
            ->messageCode('theme.member.removed', ['user_id' => $userId])
            ->json();
    }
}
