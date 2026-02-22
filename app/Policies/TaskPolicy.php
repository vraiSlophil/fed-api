<?php

namespace App\Policies;

use App\Models\Auth\User;
use App\Models\Tasks\Task;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return $task->user_id === $user->user_id || $task->theme->canBeViewedBy($user->user_id);
    }

    public function update(User $user, Task $task): bool
    {
        return $task->theme->canEditTaskBy($user->user_id);
    }

    public function delete(User $user, Task $task): bool
    {
        return $task->theme->canDeleteTaskBy($user->user_id);
    }

    public function validate(User $user, Task $task): bool
    {
        return $task->theme->canValidateTaskBy($user->user_id);
    }

    public function archive(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    public function restore(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }
}
