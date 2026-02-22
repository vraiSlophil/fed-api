<?php

namespace App\Models\Tasks;

use App\Domain\Tasks\Enums\TaskStatus;
use App\Models\Auth\User;
use App\Models\Reminders\Reminder;
use App\Models\Themes\Theme;
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
     * Vérifie si une tâche est validée
     */
    public function isValidated(): bool
    {
        return $this->validated_at !== null;
    }

    /**
     * Valide une tâche
     */
    public function validate(): self
    {
        $this->status = TaskStatus::DONE;
        $this->validated_at = now();
        $this->completed_at = now();

        return $this;
    }

    /**
     * Invalide une tâche (enlève la validation)
     */
    public function invalidate(): self
    {
        $this->validated_at = null;

        return $this;
    }

    /**
     * Gère automatiquement la mise à jour du statut
     */
    public function setStatusAttribute($value): void
    {
        $status = $value instanceof TaskStatus ? $value : TaskStatus::fromInput((string) $value);
        $this->attributes['status'] = $status->value;

        // Si marquée comme terminée, ajouter la date de validation
        if ($status === TaskStatus::DONE && $this->validated_at === null) {
            $this->attributes['validated_at'] = now();
            $this->attributes['completed_at'] = now();
        }

        // Si on change le statut de done à autre chose, retirer la date de validation
        if ($status !== TaskStatus::DONE && $this->validated_at !== null) {
            $this->attributes['validated_at'] = null;
            $this->attributes['completed_at'] = null;
        }
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class, 'theme_id', 'theme_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id', 'task_id');
    }

    public function subTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id', 'task_id');
    }

    public function dependenciesFrom(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'from_task_id', 'task_id');
    }

    public function dependenciesTo(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'to_task_id', 'task_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'task_id', 'task_id');
    }
}
