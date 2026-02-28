<?php

namespace App\Policies;

use App\Models\Auth\User;

class AdminUserPolicy
{
    /**
     * Determine whether the user can view the resource list.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function viewAny(User $user): bool
    {
        return $user->role_power >= 100;
    }

    /**
     * Determine whether the user can view the specified resource.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  User  $target  Target user instance affected by this operation.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function view(User $user, User $target): bool
    {
        return $user->role_power >= 100;
    }

    /**
     * Determine whether the user can create a new resource.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function create(User $user): bool
    {
        return $user->role_power >= 100;
    }

    /**
     * Determine whether the user can update the specified resource.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  User  $target  Target user instance affected by this operation.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function update(User $user, User $target): bool
    {
        return $user->role_power >= 100 || $user->user_id === $target->user_id;
    }

    /**
     * Determine whether the user can delete the specified resource.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  User  $target  Target user instance affected by this operation.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function delete(User $user, User $target): bool
    {
        return $user->role_power >= 100 && $user->user_id !== $target->user_id;
    }
}
