<?php

namespace App\Models\Auth;

use App\Models\Billing\UserSubscription;
use App\Models\Metrics\UserMetric;
use App\Models\Playgrounds\Playground;
use App\Models\Reminders\Reminder;
use App\Models\Tasks\Task;
use App\Models\Themes\Theme;
use App\Models\Themes\ThemeTemplate;
use App\Models\Themes\ThemeUserPermission;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'username',
        'email',
        'password',
        'last_name',
        'first_name',
        'avatar_path',
        'last_login_at',
        'last_login_ip',
        'role_power',
        'blocked_at',
        'active_playground_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'settings' => 'array',
            'password' => 'hashed',
            'blocked_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function metric(): HasOne
    {
        return $this->hasOne(UserMetric::class, 'user_id', 'user_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'user_id', 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_power', 'power');
    }

    public function themes(): HasMany
    {
        return $this->hasMany(Theme::class, 'owner_id', 'user_id');
    }

    public function playgrounds(): HasMany
    {
        return $this->hasMany(Playground::class, 'user_id', 'user_id');
    }

    public function activePlayground(): BelongsTo
    {
        return $this->belongsTo(Playground::class, 'active_playground_id', 'playground_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'user_id', 'user_id');
    }

    public function themeTemplates(): HasMany
    {
        return $this->hasMany(ThemeTemplate::class, 'user_id', 'user_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class, 'user_id', 'user_id');
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(UserSubscription::class, 'user_id', 'user_id')
            ->where('status', 'active')
            ->orWhere('status', 'trialing')
            ->latest('started_at');
    }

    /**
     * Send the email verification notification on the email queue.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new QueuedVerifyEmail);
    }

    /**
     * Send the password reset notification on the reset queue.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new QueuedResetPassword($token));
    }

    /**
     * Vérifie si l'utilisateur est bloqué
     */
    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    /**
     * Bloque l'utilisateur
     */
    public function block(): void
    {
        $this->update(['blocked_at' => now()]);
    }

    /**
     * Débloque l'utilisateur
     */
    public function unblock(): void
    {
        $this->update(['blocked_at' => null]);
    }

    /**
     * Relation vers les permissions de thèmes
     */
    public function themeUserPermissions(): HasMany
    {
        return $this->hasMany(ThemeUserPermission::class, 'user_id', 'user_id');
    }
}
