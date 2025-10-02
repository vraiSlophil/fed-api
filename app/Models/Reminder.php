<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id', 'task_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(ReminderNotification::class, 'reminder_id', 'reminder_id');
    }

    /**
     * Vérifie si le rappel est actif
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Vérifie si le rappel est en pause
     */
    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    /**
     * Vérifie si le rappel est terminé
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Vérifie si le rappel est annulé
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
