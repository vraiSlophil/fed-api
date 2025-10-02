<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class, 'reminder_id', 'reminder_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Vérifie si la notification a été délivrée
     */
    public function isDelivered(): bool
    {
        return $this->delivered_at !== null;
    }

    /**
     * Marque la notification comme délivrée
     */
    public function markAsDelivered(): self
    {
        $this->delivered_at = now();
        $this->save();
        return $this;
    }
}
