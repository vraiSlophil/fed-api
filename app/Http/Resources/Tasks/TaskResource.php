<?php

namespace App\Http\Resources\Tasks;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * @mixin \App\Models\Tasks\Task
 */
class TaskResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->value('status');

        if ($status instanceof \BackedEnum) {
            $status = $status->value;
        }

        return [
            'task_id' => $this->value('task_id'),
            'theme_id' => $this->value('theme_id'),
            'user_id' => $this->value('user_id'),
            'title' => $this->value('title'),
            'description' => $this->value('description'),
            'status' => $status,
            'position' => $this->value('position'),
            'priority' => $this->value('priority'),
            'due_at' => $this->value('due_at'),
            'parent_task_id' => $this->value('parent_task_id'),
            'metadata' => $this->value('metadata'),
            'validated_at' => $this->value('validated_at'),
            'completed_at' => $this->value('completed_at'),
            'archived_at' => $this->value('archived_at'),
            'created_at' => $this->value('created_at'),
            'updated_at' => $this->value('updated_at'),
        ];
    }
}
