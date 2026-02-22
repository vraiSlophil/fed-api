<?php

namespace App\Policies;

use App\Models\Auth\User;
use App\Models\Themes\Theme;

class ThemePolicy
{
    public function view(User $user, Theme $theme): bool
    {
        return $theme->canBeViewedBy($user->user_id);
    }

    public function update(User $user, Theme $theme): bool
    {
        return $theme->canBeUpdatedBy($user->user_id);
    }

    public function delete(User $user, Theme $theme): bool
    {
        return $theme->isOwnedBy($user->user_id);
    }

    public function manageMembers(User $user, Theme $theme): bool
    {
        return $theme->isOwnedBy($user->user_id);
    }

    public function addTask(User $user, Theme $theme): bool
    {
        return $theme->canAddTaskBy($user->user_id);
    }
}
