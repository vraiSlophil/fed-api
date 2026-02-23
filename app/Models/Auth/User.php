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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the model attributes that should be cast.
     *
     * @return array Model cast definitions keyed by attribute name.
     */
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

    /**
     * Define the one-to-one relationship to user metric using user_id and user_id keys.
     *
     * @return HasOne Configured relationship query definition.
     */
    public function metric(): HasOne
    {
        return $this->hasOne(UserMetric::class, 'user_id', 'user_id');
    }

    /**
     * Define the one-to-many relationship to task using user_id and user_id keys.
     *
     * @return HasMany Configured relationship query definition.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'user_id', 'user_id');
    }

    /**
     * Define the belongs-to relationship to role using role_power and power keys.
     *
     * @return BelongsTo Configured relationship query definition.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_power', 'power');
    }

    /**
     * Define the one-to-many relationship to theme using owner_id and user_id keys.
     *
     * @return HasMany Configured relationship query definition.
     */
    public function themes(): HasMany
    {
        return $this->hasMany(Theme::class, 'owner_id', 'user_id');
    }

    /**
     * Define the one-to-many relationship to playground using user_id and user_id keys.
     *
     * @return HasMany Configured relationship query definition.
     */
    public function playgrounds(): HasMany
    {
        return $this->hasMany(Playground::class, 'user_id', 'user_id');
    }

    /**
     * Define the one-to-many relationship to reminder using user_id and user_id keys.
     *
     * @return HasMany Configured relationship query definition.
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'user_id', 'user_id');
    }

    /**
     * Define the one-to-many relationship to theme template using user_id and user_id keys.
     *
     * @return HasMany Configured relationship query definition.
     */
    public function themeTemplates(): HasMany
    {
        return $this->hasMany(ThemeTemplate::class, 'user_id', 'user_id');
    }

    /**
     * Define the one-to-many relationship to user subscription using user_id and user_id keys.
     *
     * @return HasMany Configured relationship query definition.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class, 'user_id', 'user_id');
    }

    /**
     * Define the one-to-one relationship to user subscription using user_id and user_id keys.
     *
     * @return HasOne Configured relationship query definition.
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(UserSubscription::class, 'user_id', 'user_id')
            ->whereIn('status', ['active', 'trialing'])
            ->latest('started_at');
    }

    /**
     * Send email verification notification.
     *
     * @return void No return value.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new QueuedVerifyEmail);
    }

    /**
     * Send password reset notification.
     *
     * @param  mixed  $token  Password-reset token generated by Laravel's password broker.
     * @return void No return value.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new QueuedResetPassword($token));
    }

    /**
     * Determine whether the user account is currently blocked.
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    /**
     * Block the targeted user account.
     *
     * @return void No return value.
     */
    public function block(): void
    {
        $this->update(['blocked_at' => now()]);
    }

    /**
     * Unblock the targeted user account.
     *
     * @return void No return value.
     */
    public function unblock(): void
    {
        $this->update(['blocked_at' => null]);
    }

    /**
     * Define the one-to-many relationship to theme user permission using user_id and user_id keys.
     *
     * @return HasMany Configured relationship query definition.
     */
    public function themeUserPermissions(): HasMany
    {
        return $this->hasMany(ThemeUserPermission::class, 'user_id', 'user_id');
    }
}
