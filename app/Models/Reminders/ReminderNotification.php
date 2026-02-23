<?php

namespace App\Models\Reminders;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderNotification extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'notification_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'reminder_id',
        'user_id',
        'event_time',
        'delivered_at',
        'attempts',
        'error',
    ];

    protected $casts = [
        'event_time' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    /**
     * Define the belongs-to relationship to reminder using reminder_id and reminder_id keys.
     *
     * @return BelongsTo Configured relationship query definition.
     */
    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class, 'reminder_id', 'reminder_id');
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
     * Determine whether the reminder notification has been delivered.
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function isDelivered(): bool
    {
        return $this->delivered_at !== null;
    }

    /**
     * Mark the reminder notification as delivered.
     *
     * @return self Current instance for fluent chaining.
     */
    public function markAsDelivered(): self
    {
        $this->delivered_at = now();
        $this->save();

        return $this;
    }
}
