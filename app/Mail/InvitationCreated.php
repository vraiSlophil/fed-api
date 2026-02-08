<?php

namespace App\Mail;

use App\Models\Invitation;
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

    public function __construct(Invitation $invitation, string $acceptLink, string $declineLink)
    {
        $this->invitation = $invitation;
        $this->acceptLink = $acceptLink;
        $this->declineLink = $declineLink;
    }

    public function build(): self
    {
        $resource = class_basename($this->invitation->invitable_type);

        return $this->subject("Invitation a rejoindre {$resource}")
            ->markdown('emails.invitations.created')
            ->with([
                'invitation' => $this->invitation,
            ]);
    }

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
