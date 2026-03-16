<?php

namespace App\Mail;

use App\Models\Invitations\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class InvitationCreated extends Mailable
{
    use Queueable, SerializesModels;

    public $tries = 3;

    public Invitation $invitation;

    public string $acceptLink;

    public string $declineLink;

    /**
     * Initialize the mailable with invitation context and frontend entry links.
     *
     * @param  Invitation  $invitation  Invitation model used to render invite context.
     * @param  string  $acceptLink  Absolute URL that opens the invitation flow prefilled for acceptance.
     * @param  string  $declineLink  Absolute URL that opens the invitation flow prefilled for refusal.
     */
    public function __construct(Invitation $invitation, string $acceptLink, string $declineLink)
    {
        $this->invitation = $invitation;
        $this->acceptLink = $acceptLink;
        $this->declineLink = $declineLink;
    }

    /**
     * Build the invitation-created email message.
     *
     * @return self Current instance for fluent chaining.
     */
    public function build(): self
    {
        $resource = class_basename($this->invitation->invitable_type);

        return $this->subject("Invitation to join {$resource}")
            ->markdown('emails.invitations.created')
            ->with([
                'invitation' => $this->invitation,
            ]);
    }

    /**
     * Log queue delivery failures for the invitation-created email.
     *
     * @param  Throwable  $e  Exception captured by the failure callback.
     * @return void No return value.
     */
    public function failed(Throwable $e): void
    {
        Log::error('Invitation created email failed', [
            'invitation_id' => $this->invitation->invitation_id ?? null,
            'inviter_user_id' => $this->invitation->inviter_user_id ?? null,
            'invitee_user_id' => $this->invitation->invitee_user_id ?? null,
            'error' => $e->getMessage(),
        ]);
    }
}
