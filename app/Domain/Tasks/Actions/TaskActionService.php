<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Tasks\Enums\TaskStatus;
use App\Models\Auth\User;
use App\Models\Tasks\Task;
use App\Models\Themes\Theme;
use Illuminate\Auth\Access\AuthorizationException;

class TaskActionService
{
    /**
     * Create a task inside a theme when the user has task-creation permission.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @param  array  $validated  Validated payload extracted from the request.
     * @return Task Task instance returned after successful execution.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException When the operation cannot be completed.
     */
    public function create(User $user, Theme $theme, array $validated): Task
    {
        if (! $theme->canAddTaskBy($user->user_id)) {
            throw new AuthorizationException('Forbidden');
        }

        $status = isset($validated['status'])
            ? TaskStatus::fromInput((string) $validated['status'])->value
            : TaskStatus::TODO->value;

        return Task::create([
            'theme_id' => $theme->theme_id,
            'user_id' => $user->user_id,
            'title' => $validated['title'],
            'status' => $status,
        ]);
    }

    /**
     * Update task fields and enforce completion permissions for status transitions.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Task  $task  Task instance being read or mutated by this method.
     * @param  array  $validated  Validated payload extracted from the request.
     * @return Task Task instance returned after successful execution.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException When the operation cannot be completed.
     */
    public function update(User $user, Task $task, array $validated): Task
    {
        $theme = $task->theme;

        if (! $theme->canEditTaskBy($user->user_id)) {
            throw new AuthorizationException('Forbidden');
        }

        if (isset($validated['status'])) {
            $targetStatus = TaskStatus::fromInput((string) $validated['status']);
            $validated['status'] = $targetStatus->value;

            $currentStatus = $task->status;
            $currentStatusValue = $currentStatus instanceof TaskStatus ? $currentStatus->value : (string) $currentStatus;
            if ($targetStatus === TaskStatus::DONE && $currentStatusValue !== TaskStatus::DONE->value) {
                if (! $theme->canValidateTaskBy($user->user_id)) {
                    throw new AuthorizationException('Forbidden');
                }
            }
        }

        $task->update($validated);

        return $task->fresh();
    }

    /**
     * Archive the specified task.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Task  $task  Task instance being read or mutated by this method.
     * @return Task Task instance returned after successful execution.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException When the operation cannot be completed.
     */
    public function archive(User $user, Task $task): Task
    {
        if (! $task->theme->canEditTaskBy($user->user_id)) {
            throw new AuthorizationException('Forbidden');
        }

        $task->archived_at = now();
        $task->save();

        return $task->fresh();
    }

    /**
     * Restore the specified task.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Task  $task  Task instance being read or mutated by this method.
     * @return Task Task instance returned after successful execution.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException When the operation cannot be completed.
     */
    public function restore(User $user, Task $task): Task
    {
        if (! $task->theme->canEditTaskBy($user->user_id)) {
            throw new AuthorizationException('Forbidden');
        }

        $task->archived_at = null;
        $task->save();

        return $task->fresh();
    }

    /**
     * Mark the task as completed.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Task  $task  Task instance being read or mutated by this method.
     * @return Task Task instance returned after successful execution.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException When the operation cannot be completed.
     */
    public function complete(User $user, Task $task): Task
    {
        if (! $task->theme->canValidateTaskBy($user->user_id)) {
            throw new AuthorizationException('Forbidden');
        }

        $task->status = TaskStatus::DONE;
        $task->save();

        return $task->fresh();
    }

    /**
     * Mark the task as not completed.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Task  $task  Task instance being read or mutated by this method.
     * @return Task Task instance returned after successful execution.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException When the operation cannot be completed.
     */
    public function uncomplete(User $user, Task $task): Task
    {
        if (! $task->theme->canValidateTaskBy($user->user_id)) {
            throw new AuthorizationException('Forbidden');
        }

        $task->status = TaskStatus::TODO;
        $task->save();

        return $task->fresh();
    }

    /**
     * Permanently delete a task when the user has delete permission on its theme.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Task  $task  Task instance being read or mutated by this method.
     * @return void No return value.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException When the operation cannot be completed.
     */
    public function delete(User $user, Task $task): void
    {
        if (! $task->theme->canDeleteTaskBy($user->user_id)) {
            throw new AuthorizationException('Forbidden');
        }

        $task->delete();
    }
}
