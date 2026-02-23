<?php

namespace App\Policies;

use App\Models\Auth\User;
use App\Models\Themes\Theme;

class ThemePolicy
{
    /**
     * Determine whether the user can view the specified resource.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function view(User $user, Theme $theme): bool
    {
        return $theme->canBeViewedBy($user->user_id);
    }

    /**
     * Determine whether the user can update the specified resource.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function update(User $user, Theme $theme): bool
    {
        return $theme->canBeUpdatedBy($user->user_id);
    }

    /**
     * Determine whether the user can delete the specified resource.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function delete(User $user, Theme $theme): bool
    {
        return $theme->isOwnedBy($user->user_id);
    }

    /**
     * Determine whether the user can manage members of the theme.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function manageMembers(User $user, Theme $theme): bool
    {
        return $theme->isOwnedBy($user->user_id);
    }

    /**
     * Determine whether the user can add tasks to the theme.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function addTask(User $user, Theme $theme): bool
    {
        return $theme->canAddTaskBy($user->user_id);
    }
}
