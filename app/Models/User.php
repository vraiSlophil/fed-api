<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids;

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

    public function tasks()
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
