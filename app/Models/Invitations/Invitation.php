<?php

namespace App\Models\Invitations;

use App\Models\Auth\User;
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

    /**
     * Define the belongs-to relationship to user using inviter_user_id and user_id keys.
     *
     * @return BelongsTo Configured relationship query definition.
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_user_id', 'user_id');
    }

    /**
     * Define the belongs-to relationship to user using invitee_user_id and user_id keys.
     *
     * @return BelongsTo Configured relationship query definition.
     */
    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_user_id', 'user_id');
    }

    /**
     * Define the polymorphic relation to the invitation target.
     *
     * @return MorphTo MorphTo instance returned after successful execution.
     */
    public function invitable(): MorphTo
    {
        return $this->morphTo();
    }
}
