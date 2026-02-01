<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiResponse;
use App\Models\Invitation;
use App\Models\Playground;
use App\Models\Theme;
use App\Models\ThemeUserPermission;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThemeMemberController extends Controller
{
    protected User $user;

    public function __construct(Request $request)
    {
        $this->user = $request->user();
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'required|string|min:3',
            'theme_id' => 'required|uuid|exists:themes,theme_id',
        ]);

        $search = $request->search;
        $themeId = $request->theme_id;

        $theme = Theme::findOrFail($themeId);
        $ownerId = $theme->owner_id;

        $normalizedSearch = $this->normalizeString($search);

        $users = User::whereNotNull('email_verified_at')
            ->where('user_id', '!=', $ownerId)
            ->where(function ($query) use ($normalizedSearch) {
                $query->where('username', 'like', "%{$normalizedSearch}%")
                    ->orWhere('email', 'like', "%{$normalizedSearch}%")
                    ->orWhere('first_name', 'like', "%{$normalizedSearch}%")
                    ->orWhere('last_name', 'like', "%{$normalizedSearch}%");
            })
            ->limit(10)
            ->get(['user_id', 'username', 'email', 'first_name', 'last_name', 'avatar_path']);

        $formattedUsers = $users->map(function ($user) {
            return [
                'user_id' => $user->user_id,
                'username' => $user->username,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'avatar_path' => $user->avatar_path,
            ];
        });

        return ApiResponse::builder()
            ->success()
            ->messageCode('theme.users.search.success')
            ->data([
                'users' => $formattedUsers,
            ])
            ->json();
    }

    public function listMembers(string $themeId): JsonResponse
    {
        $theme = $this->getThemeOrFail($themeId);

        $owner = $theme->owner;

        $permissions = $theme->themeUserPermissions()
            ->with('user')
            ->get();

        $members = $permissions->map(function ($permission) {
            return [
                'user_id' => $permission->user->user_id,
                'username' => $permission->user->username,
                'email' => $permission->user->email,
                'first_name' => $permission->user->first_name,
                'last_name' => $permission->user->last_name,
                'avatar_path' => $permission->user->avatar_path,
                'status' => $permission->status,
                'invited_at' => $permission->invited_at,
                'permissions' => [
                    'can_view' => $permission->can_view,
                    'can_update_theme' => $permission->can_update_theme,
                    'can_add_task' => $permission->can_add_task,
                    'can_edit_task' => $permission->can_edit_task,
                    'can_delete_task' => $permission->can_delete_task,
                    'can_validate_task' => $permission->can_validate_task,
                ],
            ];
        });

        $ownerData = [
            'user_id' => $owner->user_id,
            'username' => $owner->username,
            'email' => $owner->email,
            'first_name' => $owner->first_name,
            'last_name' => $owner->last_name,
            'avatar_path' => $owner->avatar_path,
            'status' => 'owner',
            'invited_at' => null,
            'permissions' => [
                'can_view' => true,
                'can_update_theme' => true,
                'can_add_task' => true,
                'can_edit_task' => true,
                'can_delete_task' => true,
                'can_validate_task' => true,
            ],
        ];

        $pendingInvitations = Invitation::where('invitable_type', Theme::class)
            ->where('invitable_id', $themeId)
            ->where('status', 'pending')
            ->with('invitee')
            ->get()
            ->map(function (Invitation $invitation) {
                $permissions = $invitation->payload['permissions'] ?? [];

                return [
                    'invitation_id' => $invitation->invitation_id,
                    'user_id' => $invitation->invitee?->user_id,
                    'username' => $invitation->invitee?->username,
                    'email' => $invitation->invitee?->email,
                    'first_name' => $invitation->invitee?->first_name,
                    'last_name' => $invitation->invitee?->last_name,
                    'avatar_path' => $invitation->invitee?->avatar_path,
                    'status' => 'invited',
                    'invited_at' => $invitation->created_at,
                    'permissions' => [
                        'can_view' => (bool) ($permissions['can_view'] ?? false),
                        'can_update_theme' => (bool) ($permissions['can_update_theme'] ?? false),
                        'can_add_task' => (bool) ($permissions['can_add_task'] ?? false),
                        'can_edit_task' => (bool) ($permissions['can_edit_task'] ?? false),
                        'can_delete_task' => (bool) ($permissions['can_delete_task'] ?? false),
                        'can_validate_task' => (bool) ($permissions['can_validate_task'] ?? false),
                    ],
                ];
            });

        $allMembers = collect([$ownerData])->merge($members)->merge($pendingInvitations);

        return ApiResponse::builder()
            ->success()
            ->messageCode('theme.members.list.success')
            ->data([
                'members' => $allMembers,
            ])
            ->json();
    }

    public function inviteUser(Request $request, string $themeId): JsonResponse
    {
        $theme = $this->getThemeOrFail($themeId);

        $validated = $request->validate([
            'user_id' => 'required|uuid|exists:users,user_id',
            'can_view' => 'required|boolean',
            'can_update_theme' => 'required|boolean',
            'can_add_task' => 'required|boolean',
            'can_edit_task' => 'required|boolean',
            'can_delete_task' => 'required|boolean',
            'can_validate_task' => 'required|boolean',
        ]);

        if ($theme->owner_id === $validated['user_id']) {
            throw new ApiException('permission.denied', [], 403, 'Cannot invite theme owner');
        }

        if (ThemeUserPermission::where('theme_id', $themeId)
            ->where('user_id', $validated['user_id'])
            ->first()) {
            throw new ApiException('theme.member.already_exists', ['user_id' => $validated['user_id']], 409, 'User is already a member of this theme');
        }

        if (Invitation::where('invitee_user_id', $validated['user_id'])
            ->where('invitable_type', Theme::class)
            ->where('invitable_id', $themeId)
            ->exists()) {
            throw new ApiException('theme.invitation.already_exists', ['user_id' => $validated['user_id']], 409, 'User has already been invited to this theme');
        }

        $invitedUser = User::findOrFail($validated['user_id']);
        $expiresAt = now()->addDays((int) config('invitations.expires_days', 7));
        $payload = [
            'model' => 'theme',
            'permissions' => [
                'can_view' => $validated['can_view'],
                'can_update_theme' => $validated['can_update_theme'],
                'can_add_task' => $validated['can_add_task'],
                'can_edit_task' => $validated['can_edit_task'],
                'can_delete_task' => $validated['can_delete_task'],
                'can_validate_task' => $validated['can_validate_task'],
            ],
        ];

        $invitation = Invitation::create([
            'inviter_user_id' => $this->user->user_id,
            'invitee_user_id' => $invitedUser->user_id,
            'invitable_type' => Theme::class,
            'invitable_id' => $themeId,
            'payload' => $payload,
            'status' => 'pending',
            'expires_at' => $expiresAt,
        ]);

        app(InvitationService::class)->sendCreatedEmail($invitation);

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
                    'invited_at' => $invitation->created_at,
                ],
            ])
            ->json();
    }

    public function updateMemberPermissions(Request $request, string $themeId, string $userId): JsonResponse
    {
        $theme = $this->getThemeOrFail($themeId);

        $validated = $request->validate([
            'can_view' => 'required|boolean',
            'can_update_theme' => 'required|boolean',
            'can_add_task' => 'required|boolean',
            'can_edit_task' => 'required|boolean',
            'can_delete_task' => 'required|boolean',
            'can_validate_task' => 'required|boolean',
        ]);

        $permission = ThemeUserPermission::where('theme_id', $themeId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $permission->update([
            'can_view' => $validated['can_view'],
            'can_update_theme' => $validated['can_update_theme'],
            'can_add_task' => $validated['can_add_task'],
            'can_edit_task' => $validated['can_edit_task'],
            'can_delete_task' => $validated['can_delete_task'],
            'can_validate_task' => $validated['can_validate_task'],
        ]);

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

    public function deactivateMember(string $themeId, string $userId): JsonResponse
    {
        $theme = $this->getThemeOrFail($themeId);

        if ($theme->owner_id === $userId) {
            throw new ApiException('permission.denied', [], 400, 'Cannot deactivate theme owner');
        }

        $permission = ThemeUserPermission::where('theme_id', $themeId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $permission->status = 'revoked';
        $permission->save();

        return ApiResponse::builder()
            ->success(200)
            ->messageCode('theme.member.deactivated', ['user_id' => $userId])
            ->json();
    }

    public function reactivateMember(string $themeId, string $userId): JsonResponse
    {
        $permission = ThemeUserPermission::where('theme_id', $themeId)
            ->where('user_id', $userId)
            ->where('status', 'revoked')
            ->firstOrFail();

        $permission->status = 'active';
        $permission->save();

        return ApiResponse::builder()
            ->success(200)
            ->messageCode('theme.member.reactivated', ['user_id' => $userId])
            ->json();
    }

    public function removeMember(string $themeId, string $userId): JsonResponse
    {
        $theme = $this->getThemeOrFail($themeId);

        if ($theme->owner_id === $userId) {
            throw new ApiException('permission.denied', [], 400, 'Cannot remove theme owner');
        }

        $permission = ThemeUserPermission::where('theme_id', $themeId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $permission->delete();

        return ApiResponse::builder()
            ->success(200)
            ->messageCode('theme.member.removed', ['user_id' => $userId])
            ->json();
    }

    public function leaveTheme(string $themeId): JsonResponse
    {
        $userId = $this->user->user_id;

        $theme = Theme::findOrFail($themeId);
        if ($theme->owner_id === $userId) {
            throw new ApiException('permission.denied', [], 400, 'Owner cannot leave theme');
        }

        $permission = ThemeUserPermission::where('theme_id', $themeId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $permission->delete();

        return ApiResponse::builder()
            ->success(200)
            ->messageCode('theme.member.left', ['user_id' => $userId])
            ->json();
    }

    public function moveToPlayground(Request $request, string $themeId): JsonResponse
    {
        $userId = $request->user()->user_id;

        $validated = $request->validate([
            'target_playground_id' => 'required|uuid|exists:playgrounds,playground_id',
        ]);

        $playground = Playground::where('playground_id', $validated['target_playground_id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        $permission = ThemeUserPermission::where('theme_id', $themeId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->firstOrFail();

        $permission->update([
            'target_playground_id' => $validated['target_playground_id'],
        ]);

        return ApiResponse::builder()
            ->success(200)
            ->messageCode('theme.move.success', ['target_playground_id' => $validated['target_playground_id']])
            ->data([
                'permission' => $permission->fresh(['theme', 'targetPlayground']),
            ])
            ->json();
    }

    private function getThemeOrFail(string $themeId): Theme
    {
        $userId = $this->user->user_id;

        return Theme::where('theme_id', $themeId)
            ->where('owner_id', $userId)
            ->firstOrFail();
    }

    private function normalizeString(string $string): string
    {
        $string = mb_strtolower($string, 'UTF-8');

        return transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $string);
    }
}
