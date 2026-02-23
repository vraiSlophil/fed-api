<?php

namespace App\Observers;

use App\Models\Metrics\UserMetric;
use App\Models\Tasks\Task;

class TaskObserver
{
    /**
     * Handle side effects triggered after model creation.
     *
     * @param  Task  $task  Task instance being read or mutated by this method.
     * @return void No return value.
     */
    public function created(Task $task): void
    {
        UserMetric::updateUserMetrics($task->user_id);
    }

    /**
     * Handle side effects triggered after model updates.
     *
     * @param  Task  $task  Task instance being read or mutated by this method.
     * @return void No return value.
     */
    public function updated(Task $task): void
    {
        if ($task->wasChanged('status')) {
            UserMetric::updateUserMetrics($task->user_id);
        }
    }

    /**
     * Handle side effects triggered after model deletion.
     *
     * @param  Task  $task  Task instance being read or mutated by this method.
     * @return void No return value.
     */
    public function deleted(Task $task): void
    {
        UserMetric::updateUserMetrics($task->user_id);
    }
}
