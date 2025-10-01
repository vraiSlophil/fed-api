<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class, 'plan_id', 'plan_id');
    }

    /**
     * Vérifie si le plan est gratuit
     */
    public function isFree(): bool
    {
        return $this->code === 'freemium' || ($this->monthly_price === null && $this->yearly_price === null);
    }

    /**
     * Vérifie si le plan est Pro
     */
    public function isPro(): bool
    {
        return $this->code === 'pro';
    }

    /**
     * Récupère une fonctionnalité spécifique du plan
     */
    public function getFeature(string $key, $default = null)
    {
        return $this->features[$key] ?? $default;
    }

    /**
     * Vérifie si le plan a une fonctionnalité
     */
    public function hasFeature(string $key): bool
    {
        return isset($this->features[$key]) && $this->features[$key] === true;
    }
}
