<?php

namespace App\Domain\ThemeMembers\Actions;

use App\Domain\Themes\Support\ThemePermissionInvariant;
use App\Exceptions\ApiException;
use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;
use App\Models\Themes\Theme;
use App\Models\Themes\ThemeUserPermission;

class ThemeMemberActionService
{
    /**
     * Update permission flags for the specified theme member.
     *
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @param  string  $userId  Identifier of the user.
     * @param  array  $validated  Validated payload extracted from the request.
     * @return ThemeUserPermission ThemeUserPermission instance returned after successful execution.
     */
    public function updateMemberPermissions(Theme $theme, string $userId, array $validated): ThemeUserPermission
    {
        if (array_key_exists('status', $validated) && $theme->owner_id === $userId) {
            throw new ApiException('permission.denied', [], 400, 'Cannot update theme owner status');
        }

        $permission = ThemeUserPermission::query()
            ->where('theme_id', $theme->theme_id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $finalPermissions = [
            'can_view' => array_key_exists('can_view', $validated) ? (bool) $validated['can_view'] : (bool) $permission->can_view,
            'can_update_theme' => array_key_exists('can_update_theme', $validated) ? (bool) $validated['can_update_theme'] : (bool) $permission->can_update_theme,
            'can_add_task' => array_key_exists('can_add_task', $validated) ? (bool) $validated['can_add_task'] : (bool) $permission->can_add_task,
            'can_edit_task' => array_key_exists('can_edit_task', $validated) ? (bool) $validated['can_edit_task'] : (bool) $permission->can_edit_task,
            'can_delete_task' => array_key_exists('can_delete_task', $validated) ? (bool) $validated['can_delete_task'] : (bool) $permission->can_delete_task,
            'can_validate_task' => array_key_exists('can_validate_task', $validated) ? (bool) $validated['can_validate_task'] : (bool) $permission->can_validate_task,
        ];

        ThemePermissionInvariant::ensureCanViewForActionFlags($finalPermissions);

        $updates = [];
        foreach ([
            'can_view',
            'can_update_theme',
            'can_add_task',
            'can_edit_task',
            'can_delete_task',
            'can_validate_task',
            'status',
        ] as $field) {
            if (array_key_exists($field, $validated)) {
                $updates[$field] = $validated[$field];
            }
        }

        if ($updates !== []) {
            $permission->update($updates);
        }

        return $permission->fresh();
    }

    /**
     * Remove a member from the theme permission list.
     *
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @param  string  $userId  Identifier of the user.
     * @return void No return value.
     *
     * @throws \App\Exceptions\ApiException When the operation cannot be completed.
     */
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

    /**
     * Remove the current user from the theme membership.
     *
     * @param  User  $actor  Authenticated user who initiates the action.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @return void No return value.
     *
     * @throws \App\Exceptions\ApiException When the operation cannot be completed.
     */
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

    /**
     * Move shared theme visibility to another playground for the user.
     *
     * @param  User  $actor  Authenticated user who initiates the action.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @param  array  $validated  Validated payload extracted from the request.
     * @return ThemeUserPermission ThemeUserPermission instance returned after successful execution.
     */
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
