<?php

namespace App\Models\Reminders;

use App\Models\Auth\User;
use App\Models\Tasks\Task;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reminder extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'reminder_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'task_id',
        'title',
        'message',
        'timezone',
        'due_at',
        'rrule',
        'status',
        'occurrences_count',
        'max_occurrences',
        'last_triggered_at',
        'next_run_at',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'last_triggered_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    /**
     * Define the belongs-to relationship to user using user_id and user_id keys.
     *
     * @return BelongsTo Configured relationship query definition.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Define the belongs-to relationship to task using task_id and task_id keys.
     *
     * @return BelongsTo Configured relationship query definition.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id', 'task_id');
    }

    /**
     * Define the one-to-many relationship to reminder notification using reminder_id and reminder_id keys.
     *
     * @return HasMany Configured relationship query definition.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(ReminderNotification::class, 'reminder_id', 'reminder_id');
    }

    /**
     * Determine whether the reminder is currently active.
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Determine whether the reminder is currently paused.
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    /**
     * Determine whether the reminder has completed its lifecycle.
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Determine whether the reminder has been canceled.
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
