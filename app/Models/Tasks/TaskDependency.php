<?php

namespace App\Models\Tasks;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskDependency extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'dependency_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'from_task_id',
        'to_task_id',
        'type',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Define the belongs-to relationship to task using from_task_id and task_id keys.
     *
     * @return BelongsTo Configured relationship query definition.
     */
    public function fromTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'from_task_id', 'task_id');
    }

    /**
     * Define the belongs-to relationship to task using to_task_id and task_id keys.
     *
     * @return BelongsTo Configured relationship query definition.
     */
    public function toTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'to_task_id', 'task_id');
    }

    /**
     * Determine whether the dependency is of type "blocking".
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function isBlocking(): bool
    {
        return $this->type === 'blocking';
    }

    /**
     * Determine whether the dependency is of type "sequential".
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function isSequential(): bool
    {
        return $this->type === 'sequential';
    }

    /**
     * Determine whether the dependency is of type "soft".
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function isSoft(): bool
    {
        return $this->type === 'soft';
    }
}
