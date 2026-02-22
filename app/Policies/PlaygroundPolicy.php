<?php

namespace App\Policies;

use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;

class PlaygroundPolicy
{
    public function view(User $user, Playground $playground): bool
    {
        return $playground->user_id === $user->user_id;
    }

    public function update(User $user, Playground $playground): bool
    {
        return $playground->user_id === $user->user_id;
    }

    public function delete(User $user, Playground $playground): bool
    {
        return $playground->user_id === $user->user_id;
    }

    public function setDefault(User $user, Playground $playground): bool
    {
        return $playground->user_id === $user->user_id;
    }

    public function stats(User $user, Playground $playground): bool
    {
        return $playground->user_id === $user->user_id;
    }
}
