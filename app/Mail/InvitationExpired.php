<?php

namespace App\Mail;

use App\Models\Invitations\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class InvitationExpired extends Mailable
{
    use Queueable, SerializesModels;

    public Invitation $invitation;

    /**
     * Initialize the mailable with the invitation that reached expiration.
     *
     * @param  Invitation  $invitation  Invitation model used to render expiration details.
     */
    public function __construct(Invitation $invitation)
    {
        $this->invitation = $invitation;
    }

    /**
     * Build the invitation-expired email message.
     *
     * @return self Current instance for fluent chaining.
     */
    public function build(): self
    {
        return $this->subject('Invitation expired')
            ->markdown('emails.invitations.expired');
    }

    /**
     * Log queue delivery failures for the invitation-expired email.
     *
     * @param  Throwable  $e  Exception captured by the failure callback.
     * @return void No return value.
     */
    public function failed(Throwable $e): void
    {
        Log::error('Invitation expired email failed', [
            'invitation_id' => $this->invitation->invitation_id ?? null,
            'inviter_user_id' => $this->invitation->inviter_user_id ?? null,
            'error' => $e->getMessage(),
        ]);
    }
}
