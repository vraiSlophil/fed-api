<?php

namespace App\Domain\ThemeMembers\Actions;

use App\Domain\Invitations\Services\InvitationService;
use App\Exceptions\ApiException;
use App\Models\Auth\User;
use App\Models\Invitations\Invitation;
use App\Models\Playgrounds\Playground;
use App\Models\Themes\Theme;
use App\Models\Themes\ThemeUserPermission;

class ThemeMemberActionService
{
    public function inviteUser(User $actor, Theme $theme, array $validated, InvitationService $invitationService): array
    {
        if ($theme->owner_id === $validated['user_id']) {
            throw new ApiException('permission.denied', [], 403, 'Cannot invite theme owner');
        }

        if (ThemeUserPermission::query()
            ->where('theme_id', $theme->theme_id)
            ->where('user_id', $validated['user_id'])
            ->exists()) {
            throw new ApiException('theme.member.already_exists', ['user_id' => $validated['user_id']], 409, 'User is already a member of this theme');
        }

        if (Invitation::query()
            ->where('invitee_user_id', $validated['user_id'])
            ->where('invitable_type', Theme::class)
            ->where('invitable_id', $theme->theme_id)
            ->where('status', 'pending')
            ->exists()) {
            throw new ApiException('theme.invitation.already_exists', ['user_id' => $validated['user_id']], 409, 'User has already been invited to this theme');
        }

        $invitedUser = User::query()->findOrFail($validated['user_id']);
        $expiresAt = now()->addDays((int) config('invitations.expires_days', 7));

        $invitation = Invitation::create([
            'inviter_user_id' => $actor->user_id,
            'invitee_user_id' => $invitedUser->user_id,
            'invitable_type' => Theme::class,
            'invitable_id' => $theme->theme_id,
            'payload' => [
                'model' => 'theme',
                'permissions' => [
                    'can_view' => $validated['can_view'],
                    'can_update_theme' => $validated['can_update_theme'],
                    'can_add_task' => $validated['can_add_task'],
                    'can_edit_task' => $validated['can_edit_task'],
                    'can_delete_task' => $validated['can_delete_task'],
                    'can_validate_task' => $validated['can_validate_task'],
                ],
            ],
            'status' => 'pending',
            'expires_at' => $expiresAt,
        ]);

        $invitationService->sendCreatedEmail($invitation);

        return [
            'invitation' => $invitation,
            'invited_user' => $invitedUser,
        ];
    }

    public function updateMemberPermissions(Theme $theme, string $userId, array $validated): ThemeUserPermission
    {
        $permission = ThemeUserPermission::query()
            ->where('theme_id', $theme->theme_id)
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

        return $permission->fresh();
    }

    public function deactivateMember(Theme $theme, string $userId): void
    {
        if ($theme->owner_id === $userId) {
            throw new ApiException('permission.denied', [], 400, 'Cannot deactivate theme owner');
        }

        $permission = ThemeUserPermission::query()
            ->where('theme_id', $theme->theme_id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $permission->status = 'revoked';
        $permission->save();
    }

    public function reactivateMember(Theme $theme, string $userId): void
    {
        $permission = ThemeUserPermission::query()
            ->where('theme_id', $theme->theme_id)
            ->where('user_id', $userId)
            ->where('status', 'revoked')
            ->firstOrFail();

        $permission->status = 'active';
        $permission->save();
    }

    public function removeMember(Theme $theme, string $userId): void
    {
        if ($theme->owner_id === $userId) {
            throw new ApiException('permission.denied', [], 400, 'Cannot remove theme owner');
        }

        $permission = ThemeUserPermission::query()
            ->where('theme_id', $theme->theme_id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $permission->delete();
    }

    public function leaveTheme(User $actor, Theme $theme): void
    {
        if ($theme->owner_id === $actor->user_id) {
            throw new ApiException('permission.denied', [], 400, 'Owner cannot leave theme');
        }

        $permission = ThemeUserPermission::query()
            ->where('theme_id', $theme->theme_id)
            ->where('user_id', $actor->user_id)
            ->firstOrFail();

        $permission->delete();
    }

    public function moveToPlayground(User $actor, Theme $theme, array $validated): ThemeUserPermission
    {
        Playground::query()
            ->where('playground_id', $validated['target_playground_id'])
            ->where('user_id', $actor->user_id)
            ->firstOrFail();

        $permission = ThemeUserPermission::query()
            ->where('theme_id', $theme->theme_id)
            ->where('user_id', $actor->user_id)
            ->where('status', 'active')
            ->firstOrFail();

        $permission->update([
            'target_playground_id' => $validated['target_playground_id'],
        ]);

        return $permission->fresh(['theme', 'targetPlayground']);
    }
}
