<?php

namespace App\Mail;

use App\Models\Theme;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ThemeInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public Theme $theme;
    public User $inviter;
    public User $invitee;
    public string $invitationLink;

    /**
     * Create a new message instance.
     */
    public function __construct(Theme $theme, User $inviter, User $invitee, string $invitationLink)
    {
        $this->theme = $theme;
        $this->inviter = $inviter;
        $this->invitee = $invitee;
        $this->invitationLink = $invitationLink;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject("Invitation à rejoindre un thème")
            ->markdown('emails.theme.invitation', [
                'theme' => $this->theme,
                'inviter' => $this->inviter,
                'invitee' => $this->invitee,
                'invitationLink' => $this->invitationLink,
                'acceptLink' => $this->invitationLink . '&action=accept',
                'declineLink' => $this->invitationLink . '&action=decline',
            ]);
    }
}
