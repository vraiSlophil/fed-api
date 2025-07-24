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
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_power', 'power');
    }

    public function themes(): HasMany
    {
        return $this->hasMany(Theme::class, 'owner_id', 'user_id');
    }

    public function isBlocked(): bool
    {
        return !is_null($this->blocked_at);
    }

    public function block(): void
    {
        $this->update(['blocked_at' => now()]);
    }

    public function unblock(): void
    {
        $this->update(['blocked_at' => null]);
    }
}
