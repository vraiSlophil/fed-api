<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class InvitationDeclined extends Mailable
{
    use Queueable, SerializesModels;

    public Invitation $invitation;

    public function __construct(Invitation $invitation)
    {
        $this->invitation = $invitation;
    }

    public function build(): self
    {
        return $this->subject('Invitation refusee')
            ->markdown('emails.invitations.declined');
    }

    public function failed(Throwable $e): void
    {
        Log::error('Invitation declined email failed', [
            'invitation_id' => $this->invitation->invitation_id ?? null,
            'inviter_user_id' => $this->invitation->inviter_user_id ?? null,
            'error' => $e->getMessage(),
        ]);
    }
}
