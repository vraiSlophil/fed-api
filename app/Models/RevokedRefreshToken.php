<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevokedRefreshToken extends Model
{
    protected $table = 'revoked_refresh_tokens';

    protected $fillable = [
        'token_id',
        'user_id',
        'token_hash',
        'revoked_at',
        'expires_at',
    ];

    protected $casts = [
        'revoked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
