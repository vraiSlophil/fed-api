<?php

namespace App\Policies;

use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;

class PlaygroundPolicy
{
    /**
     * Determine whether the user can view the specified resource.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Playground  $playground  Playground targeted by the operation.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function view(User $user, Playground $playground): bool
    {
        return $playground->user_id === $user->user_id;
    }

    /**
     * Determine whether the user can update the specified resource.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Playground  $playground  Playground targeted by the operation.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function update(User $user, Playground $playground): bool
    {
        return $playground->user_id === $user->user_id;
    }

    /**
     * Determine whether the user can delete the specified resource.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Playground  $playground  Playground targeted by the operation.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function delete(User $user, Playground $playground): bool
    {
        return $playground->user_id === $user->user_id;
    }
}
