<?php

namespace App\Http\Controllers\ThemeMembers;

use App\Domain\Invitations\Services\InvitationService;
use App\Domain\ThemeMembers\Actions\ThemeMemberActionService;
use App\Domain\ThemeMembers\Queries\ThemeMemberQueryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ThemeMember\InviteThemeMemberRequest;
use App\Http\Requests\ThemeMember\MoveThemeMemberPlaygroundRequest;
use App\Http\Requests\ThemeMember\SearchThemeUsersRequest;
use App\Http\Requests\ThemeMember\UpdateThemeMemberPermissionsRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Themes\Theme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThemeMemberController extends Controller
{
    public function __construct(
        private readonly ThemeMemberQueryService $queryService,
        private readonly ThemeMemberActionService $actionService,
    ) {}

    public function searchUsers(SearchThemeUsersRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $theme = $this->queryService->findTheme((string) $validated['theme_id']);

        $this->authorize('manageMembers', $theme);

        $users = $this->queryService->searchUsers($theme, (string) $validated['search']);

        return ApiResponse::builder()
            ->success()
            ->messageCode('theme.users.search.success')
            ->data([
                'users' => $users,
            ])
            ->json();
    }

    public function listMembers(Request $request, Theme $theme): JsonResponse
    {
        $this->authorize('manageMembers', $theme);

        $members = $this->queryService->listMembers($theme);

        return ApiResponse::builder()
            ->success()
            ->messageCode('theme.members.list.success')
            ->data([
                'members' => $members,
            ])
            ->json();
    }

    public function inviteUser(
        InviteThemeMemberRequest $request,
        Theme $theme,
        InvitationService $invitationService,
    ): JsonResponse {
        $this->authorize('manageMembers', $theme);

        $result = $this->actionService->inviteUser(
            $request->user(),
            $theme,
            $request->validated(),
            $invitationService,
        );

        $invitation = $result['invitation'];
        $invitedUser = $result['invited_user'];

        return ApiResponse::builder()
            ->success(201)
            ->messageCode('theme.invite.sent', ['email' => $invitedUser->email])
            ->data([
                'invitation' => [
                    'invitation_id' => $invitation->invitation_id,
                    'user_id' => $invitedUser->user_id,
                    'username' => $invitedUser->username,
                    'email' => $invitedUser->email,
                    'first_name' => $invitedUser->first_name,
                    'last_name' => $invitedUser->last_name,
                    'status' => $invitation->status,
                    'created_at' => $invitation->created_at,
                ],
            ])
            ->json();
    }

    public function updateMemberPermissions(
        UpdateThemeMemberPermissionsRequest $request,
        Theme $theme,
        string $userId,
    ): JsonResponse {
        $this->authorize('manageMembers', $theme);

        $permission = $this->actionService->updateMemberPermissions($theme, $userId, $request->validated());

        return ApiResponse::builder()
            ->success(200)
            ->messageCode('theme.member.permissions.updated', ['user_id' => $userId])
            ->data([
                'permissions' => [
                    'can_view' => $permission->can_view,
                    'can_update_theme' => $permission->can_update_theme,
                    'can_add_task' => $permission->can_add_task,
                    'can_edit_task' => $permission->can_edit_task,
                    'can_delete_task' => $permission->can_delete_task,
                    'can_validate_task' => $permission->can_validate_task,
                ],
            ])
            ->json();
    }

    public function deactivateMember(Request $request, Theme $theme, string $userId): JsonResponse
    {
        $this->authorize('manageMembers', $theme);

        $this->actionService->deactivateMember($theme, $userId);

        return ApiResponse::builder()
            ->success(200)
            ->messageCode('theme.member.deactivated', ['user_id' => $userId])
            ->json();
    }

    public function reactivateMember(Request $request, Theme $theme, string $userId): JsonResponse
    {
        $this->authorize('manageMembers', $theme);

        $this->actionService->reactivateMember($theme, $userId);

        return ApiResponse::builder()
            ->success(200)
            ->messageCode('theme.member.reactivated', ['user_id' => $userId])
            ->json();
    }

    public function removeMember(Request $request, Theme $theme, string $userId): JsonResponse
    {
        $this->authorize('manageMembers', $theme);

        $this->actionService->removeMember($theme, $userId);

        return ApiResponse::builder()
            ->success(200)
            ->messageCode('theme.member.removed', ['user_id' => $userId])
            ->json();
    }

    public function leaveTheme(Request $request, Theme $theme): JsonResponse
    {
        $this->authorize('view', $theme);

        $this->actionService->leaveTheme($request->user(), $theme);

        return ApiResponse::builder()
            ->success(200)
            ->messageCode('theme.member.left', ['user_id' => $request->user()->user_id])
            ->json();
    }

    public function moveToPlayground(
        MoveThemeMemberPlaygroundRequest $request,
        Theme $theme,
    ): JsonResponse {
        $this->authorize('view', $theme);

        $permission = $this->actionService->moveToPlayground($request->user(), $theme, $request->validated());

        return ApiResponse::builder()
            ->success(200)
            ->messageCode('theme.move.success', ['target_playground_id' => $request->validated('target_playground_id')])
            ->data([
                'permission' => $permission,
            ])
            ->json();
    }
}
