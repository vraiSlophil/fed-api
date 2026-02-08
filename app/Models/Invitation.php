<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Invitation extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'invitation_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'invitation_id',
        'inviter_user_id',
        'invitee_user_id',
        'invitable_type',
        'invitable_id',
        'payload',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'expires_at' => 'datetime',
    ];

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_user_id', 'user_id');
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_user_id', 'user_id');
    }

    public function invitable(): MorphTo
    {
        return $this->morphTo();
    }
}
