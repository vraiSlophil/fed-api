<?php

namespace App\Models\Billing;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSubscription extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'subscription_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'started_at',
        'renews_at',
        'ends_at',
        'cancellation_requested_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'renews_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancellation_requested_at' => 'datetime',
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
     * Define the belongs-to relationship to plan using plan_id and plan_id keys.
     *
     * @return BelongsTo Configured relationship query definition.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'plan_id');
    }

    /**
     * Determine whether the subscription is currently active.
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Determine whether the subscription is in trial period.
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function isTrialing(): bool
    {
        return $this->status === 'trialing';
    }

    /**
     * Determine whether the subscription has been canceled.
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    /**
     * Determine whether the subscription has expired.
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function isExpired(): bool
    {
        return $this->status === 'expired';
    }

    /**
     * Cancel the subscription.
     *
     * @return void No return value.
     */
    public function cancel(): void
    {
        $this->update([
            'status' => 'canceled',
            'cancellation_requested_at' => now(),
            'ends_at' => $this->renews_at ?? now(),
        ]);
    }

    /**
     * Resume the subscription.
     *
     * @return void No return value.
     */
    public function resume(): void
    {
        if ($this->isCanceled()) {
            $this->update([
                'status' => 'active',
                'cancellation_requested_at' => null,
                'ends_at' => null,
            ]);
        }
    }

    /**
     * Determine whether the subscription can be canceled.
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function canCancel(): bool
    {
        return $this->isActive() || $this->isTrialing();
    }
}
