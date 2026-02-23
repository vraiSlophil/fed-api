<?php

namespace App\Policies;

use App\Models\Auth\User;
use App\Models\Tasks\Task;

class TaskPolicy
{
    /**
     * Determine whether the user can view the specified resource.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Task  $task  Task instance being read or mutated by this method.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function view(User $user, Task $task): bool
    {
        return $task->user_id === $user->user_id || $task->theme->canBeViewedBy($user->user_id);
    }

    /**
     * Determine whether the user can update the specified resource.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Task  $task  Task instance being read or mutated by this method.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function update(User $user, Task $task): bool
    {
        return $task->theme->canEditTaskBy($user->user_id);
    }

    /**
     * Determine whether the user can delete the specified resource.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Task  $task  Task instance being read or mutated by this method.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function delete(User $user, Task $task): bool
    {
        return $task->theme->canDeleteTaskBy($user->user_id);
    }

    /**
     * Validate the specified task.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Task  $task  Task instance being read or mutated by this method.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function validate(User $user, Task $task): bool
    {
        return $task->theme->canValidateTaskBy($user->user_id);
    }

    /**
     * Archive the specified task.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Task  $task  Task instance being read or mutated by this method.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function archive(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    /**
     * Restore the specified task.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Task  $task  Task instance being read or mutated by this method.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function restore(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }
}
