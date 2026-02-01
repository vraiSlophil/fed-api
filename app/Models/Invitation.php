<?php

namespace App\Models;

use App\Mail\InvitationAccepted;
use App\Mail\InvitationDeclined;
use App\Mail\InvitationExpired;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Mail;

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

    public function markAccepted(): void
    {
        $this->update(['status' => 'accepted']);
        $this->sendStatusEmail('accepted');
    }

    public function markDeclined(): void
    {
        $this->update(['status' => 'declined']);
        $this->sendStatusEmail('declined');
    }

    public function markExpired(): void
    {
        $this->update(['status' => 'expired']);
        $this->sendStatusEmail('expired');
    }

    private function sendStatusEmail(string $status): void
    {
        $this->loadMissing(['inviter', 'invitee', 'invitable']);

        if (! $this->inviter) {
            return;
        }

        $queue = config('queue.mail_queues.invitation', 'emails-invitation');

        $mailable = match ($status) {
            'accepted' => new InvitationAccepted($this),
            'declined' => new InvitationDeclined($this),
            'expired' => new InvitationExpired($this),
            default => null,
        };

        if ($mailable) {
            Mail::to($this->inviter->email)->queue($mailable->onQueue($queue));
        }
    }
}
