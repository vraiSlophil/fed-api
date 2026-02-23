<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'plan_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'monthly_price',
        'yearly_price',
        'features',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'features' => 'array',
    ];

    /**
     * Define the one-to-many relationship to user subscription using plan_id and plan_id keys.
     *
     * @return HasMany Configured relationship query definition.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class, 'plan_id', 'plan_id');
    }

    /**
     * Determine whether the plan is a free tier.
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function isFree(): bool
    {
        return $this->code === 'freemium' || ($this->monthly_price === null && $this->yearly_price === null);
    }

    /**
     * Determine whether the plan is the pro tier.
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function isPro(): bool
    {
        return $this->code === 'pro';
    }

    /**
     * Return the configured value for a plan feature key.
     *
     * @param  string  $key  Feature key looked up in the plan feature map.
     * @param  mixed  $default  Fallback value returned when no explicit value exists.
     * @return mixed Feature value stored in the JSON feature map, or the provided fallback.
     */
    public function getFeature(string $key, $default = null)
    {
        return $this->features[$key] ?? $default;
    }

    /**
     * Determine whether a boolean plan feature is enabled.
     *
     * @param  string  $key  Feature key looked up in the plan feature map.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function hasFeature(string $key): bool
    {
        return isset($this->features[$key]) && $this->features[$key] === true;
    }
}
