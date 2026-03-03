<?php

namespace App\Models\Tasks;

use App\Domain\Tasks\Enums\TaskStatus;
use App\Models\Auth\User;
use App\Models\Reminders\Reminder;
use App\Models\Themes\Theme;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'task_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'theme_id',
        'user_id',
        'title',
        'description',
        'status',
        'position',
        'priority',
        'due_at',
        'parent_task_id',
        'metadata',
        'validated_at',
        'completed_at',
        'archived_at',
    ];

    protected $casts = [
        'status' => TaskStatus::class,
        'metadata' => 'array',
        'due_at' => 'datetime',
        'validated_at' => 'datetime',
        'completed_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    /**
     * Scope tasks visible to a user through theme ownership or active member permissions.
     *
     * @param  Builder  $query  Query builder instance used to compose the data access query.
     * @param  string  $userId  Identifier of the user.
     * @return Builder Configured query builder instance.
     */
    public function scopeVisibleToUser(Builder $query, string $userId): Builder
    {
        return $query->whereHas('theme', function (Builder $themeQuery) use ($userId): void {
            $themeQuery->where('owner_id', $userId)
                ->orWhereHas('themeUserPermissions', function (Builder $permissionQuery) use ($userId): void {
                    $permissionQuery->where('user_id', $userId)
                        ->where('can_view', true)
                        ->where('status', 'active');
                });
        });
    }

    /**
     * Determine whether the task has already been validated.
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function isValidated(): bool
    {
        return $this->validated_at !== null;
    }

    /**
     * Validate the specified task.
     *
     * @return self Current instance for fluent chaining.
     */
    public function validate(): self
    {
        $this->status = TaskStatus::DONE;
        $this->validated_at = now();
        $this->completed_at = now();

        return $this;
    }

    /**
     * Invalidate the specified task.
     *
     * @return self Current instance for fluent chaining.
     */
    public function invalidate(): self
    {
        $this->validated_at = null;

        return $this;
    }

    /**
     * Normalize task status and keep completion timestamps in sync.
     *
     * @param  mixed  $value  Task status value before normalization.
     * @return void No return value.
     */
    public function setStatusAttribute($value): void
    {
        $status = $value instanceof TaskStatus ? $value : TaskStatus::fromInput((string) $value);
        $this->attributes['status'] = $status->value;

        // If marked as done, set the validation timestamp.
        if ($status === TaskStatus::DONE && $this->validated_at === null) {
            $this->attributes['validated_at'] = now();
            $this->attributes['completed_at'] = now();
        }

        // If status changes from done to another value, clear the validation timestamp.
        if ($status !== TaskStatus::DONE && $this->validated_at !== null) {
            $this->attributes['validated_at'] = null;
            $this->attributes['completed_at'] = null;
        }
    }

    /**
     * Define the belongs-to relationship to theme using theme_id and theme_id keys.
     *
     * @return BelongsTo Configured relationship query definition.
     */
    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class, 'theme_id', 'theme_id');
    }

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
     * Define the belongs-to relationship to task using parent_task_id and task_id keys.
     *
     * @return BelongsTo Configured relationship query definition.
     */
    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id', 'task_id');
    }

    /**
     * Define the one-to-many relationship to task using parent_task_id and task_id keys.
     *
     * @return HasMany Configured relationship query definition.
     */
    public function subTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id', 'task_id');
    }

    /**
     * Define the one-to-many relationship to task dependency using from_task_id and task_id keys.
     *
     * @return HasMany Configured relationship query definition.
     */
    public function dependenciesFrom(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'from_task_id', 'task_id');
    }

    /**
     * Define the one-to-many relationship to task dependency using to_task_id and task_id keys.
     *
     * @return HasMany Configured relationship query definition.
     */
    public function dependenciesTo(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'to_task_id', 'task_id');
    }

    /**
     * Define the one-to-many relationship to reminder using task_id and task_id keys.
     *
     * @return HasMany Configured relationship query definition.
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'task_id', 'task_id');
    }
}
