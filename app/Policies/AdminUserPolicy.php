<?php

namespace App\Policies;

use App\Models\Auth\User;

class AdminUserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role_power >= 100;
    }

    public function view(User $user, User $target): bool
    {
        return $user->role_power >= 100;
    }

    public function create(User $user): bool
    {
        return $user->role_power >= 100;
    }

    public function update(User $user, User $target): bool
    {
        return $user->role_power >= 100;
    }

    public function delete(User $user, User $target): bool
    {
        return $user->role_power >= 100 && $user->user_id !== $target->user_id;
    }

    public function block(User $user, User $target): bool
    {
        return $user->role_power >= 100 && $user->user_id !== $target->user_id;
    }

    public function unblock(User $user, User $target): bool
    {
        return $user->role_power >= 100;
    }
}
