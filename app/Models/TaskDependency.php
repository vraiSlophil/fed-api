<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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

    public function fromTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'from_task_id', 'task_id');
    }

    public function toTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'to_task_id', 'task_id');
    }

    /**
     * Vérifie si la dépendance est de type "bloquant"
     */
    public function isBlocking(): bool
    {
        return $this->type === 'blocking';
    }

    /**
     * Vérifie si la dépendance est de type "séquentiel"
     */
    public function isSequential(): bool
    {
        return $this->type === 'sequential';
    }

    /**
     * Vérifie si la dépendance est de type "soft"
     */
    public function isSoft(): bool
    {
        return $this->type === 'soft';
    }
}
