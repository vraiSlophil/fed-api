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
     * Search users eligible to be invited as theme members.
     *
     * @param  SearchThemeUsersRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @return JsonResponse JSON API response using the standard envelope.
     */
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

    /**
     * List owner, active members, and pending invites for a theme.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @return JsonResponse JSON API response using the standard envelope.
     */
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

    /**
     * Create a pending invitation for a user to join the theme.
     *
     * @param  InviteThemeMemberRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @param  InvitationService  $invitationService  Service responsible for invitation operations.
     * @return JsonResponse JSON API response using the standard envelope.
     */
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

    /**
     * Update permission flags for the specified theme member.
     *
     * @param  UpdateThemeMemberPermissionsRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @param  string  $userId  Identifier of the user.
     * @return JsonResponse JSON API response using the standard envelope.
     */
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

    /**
     * Revoke access for the specified theme member.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @param  string  $userId  Identifier of the user.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function deactivateMember(Request $request, Theme $theme, string $userId): JsonResponse
    {
        $this->authorize('manageMembers', $theme);

        $this->actionService->deactivateMember($theme, $userId);

        return ApiResponse::builder()
            ->success(200)
            ->messageCode('theme.member.deactivated', ['user_id' => $userId])
            ->json();
    }

    /**
     * Restore access for a previously revoked theme member.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @param  string  $userId  Identifier of the user.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function reactivateMember(Request $request, Theme $theme, string $userId): JsonResponse
    {
        $this->authorize('manageMembers', $theme);

        $this->actionService->reactivateMember($theme, $userId);

        return ApiResponse::builder()
            ->success(200)
            ->messageCode('theme.member.reactivated', ['user_id' => $userId])
            ->json();
    }

    /**
     * Remove a member from the theme permission list.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @param  string  $userId  Identifier of the user.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function removeMember(Request $request, Theme $theme, string $userId): JsonResponse
    {
        $this->authorize('manageMembers', $theme);

        $this->actionService->removeMember($theme, $userId);

        return ApiResponse::builder()
            ->success(200)
            ->messageCode('theme.member.removed', ['user_id' => $userId])
            ->json();
    }

    /**
     * Remove the current user from the theme membership.
     *
     * @param  Request  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @return JsonResponse JSON API response using the standard envelope.
     */
    public function leaveTheme(Request $request, Theme $theme): JsonResponse
    {
        $this->authorize('view', $theme);

        $this->actionService->leaveTheme($request->user(), $theme);

        return ApiResponse::builder()
            ->success(200)
            ->messageCode('theme.member.left', ['user_id' => $request->user()->user_id])
            ->json();
    }

    /**
     * Move shared theme visibility to another playground for the user.
     *
     * @param  MoveThemeMemberPlaygroundRequest  $request  HTTP request carrying validated parameters for this endpoint.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @return JsonResponse JSON API response using the standard envelope.
     */
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
