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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'plan_id');
    }

    /**
     * Vérifie si l'abonnement est actif
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Vérifie si l'abonnement est en période d'essai
     */
    public function isTrialing(): bool
    {
        return $this->status === 'trialing';
    }

    /**
     * Vérifie si l'abonnement est annulé
     */
    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    /**
     * Vérifie si l'abonnement a expiré
     */
    public function isExpired(): bool
    {
        return $this->status === 'expired';
    }

    /**
     * Annule l'abonnement
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
     * Réactive l'abonnement annulé
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
     * Vérifie si l'utilisateur peut annuler son abonnement
     */
    public function canCancel(): bool
    {
        return $this->isActive() || $this->isTrialing();
    }
}
