<?php

namespace App\Observers;

use App\Models\Metrics\UserMetric;
use App\Models\Tasks\Task;

class TaskObserver
{
    public function created(Task $task): void
    {
        UserMetric::updateUserMetrics($task->user_id);
    }

    public function updated(Task $task): void
    {
        if ($task->wasChanged('status')) {
            UserMetric::updateUserMetrics($task->user_id);
        }
    }

    public function deleted(Task $task): void
    {
        UserMetric::updateUserMetrics($task->user_id);
    }
}
