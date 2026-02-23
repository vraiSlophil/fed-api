<?php

namespace App\Domain\ThemeMembers\Queries;

use App\Models\Auth\User;
use App\Models\Invitations\Invitation;
use App\Models\Themes\Theme;
use Illuminate\Support\Collection;

class ThemeMemberQueryService
{
    /**
     * Load a theme by identifier for member-management operations.
     *
     * @param  string  $themeId  Identifier of the theme.
     * @return Theme Theme instance returned after successful execution.
     */
    public function findTheme(string $themeId): Theme
    {
        return Theme::query()
            ->where('theme_id', $themeId)
            ->firstOrFail();
    }

    /**
     * Search eligible users that can be invited to the theme.
     *
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @param  string  $search  Search keyword used to filter matching users.
     * @return Collection Collection of matching records.
     */
    public function searchUsers(Theme $theme, string $search): Collection
    {
        $ownerId = $theme->owner_id;

        $users = User::query()
            ->whereNotNull('email_verified_at')
            ->where('user_id', '!=', $ownerId)
            ->where(function ($query) use ($search): void {
                $query->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            })
            ->limit(10)
            ->get(['user_id', 'username', 'email', 'first_name', 'last_name', 'avatar_path']);

        return $users->map(static fn (User $user): array => [
            'user_id' => $user->user_id,
            'username' => $user->username,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'avatar_path' => $user->avatar_path,
        ]);
    }

    /**
     * Return owners, active members, and pending invitations for the theme.
     *
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @return Collection Collection of matching records.
     */
    public function listMembers(Theme $theme): Collection
    {
        $owner = $theme->owner;

        $permissions = $theme->themeUserPermissions()
            ->with('user')
            ->get();

        $members = $permissions->map(static function ($permission): array {
            return [
                'user_id' => $permission->user->user_id,
                'username' => $permission->user->username,
                'email' => $permission->user->email,
                'first_name' => $permission->user->first_name,
                'last_name' => $permission->user->last_name,
                'avatar_path' => $permission->user->avatar_path,
                'status' => $permission->status,
                'created_at' => $permission->created_at,
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
            'created_at' => null,
            'permissions' => [
                'can_view' => true,
                'can_update_theme' => true,
                'can_add_task' => true,
                'can_edit_task' => true,
                'can_delete_task' => true,
                'can_validate_task' => true,
            ],
        ];

        $pendingInvitations = Invitation::query()
            ->where('invitable_type', Theme::class)
            ->where('invitable_id', $theme->theme_id)
            ->where('status', 'pending')
            ->with('invitee')
            ->get()
            ->map(static function (Invitation $invitation): array {
                $payload = $invitation->payload;
                $permissions = is_array($payload) ? ($payload['permissions'] ?? []) : [];

                return [
                    'invitation_id' => $invitation->invitation_id,
                    'user_id' => $invitation->invitee?->user_id,
                    'username' => $invitation->invitee?->username,
                    'email' => $invitation->invitee?->email,
                    'first_name' => $invitation->invitee?->first_name,
                    'last_name' => $invitation->invitee?->last_name,
                    'avatar_path' => $invitation->invitee?->avatar_path,
                    'status' => 'invited',
                    'created_at' => $invitation->created_at,
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

        return collect([$ownerData])->merge($members)->merge($pendingInvitations);
    }
}
