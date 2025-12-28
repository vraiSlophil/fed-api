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

    public $themeModel;

    public $inviter;

    public $invitee;

    public $acceptLink;

    public $declineLink;

    /**
     * Create a new message instance.
     */
    public function __construct(Theme $theme, User $inviter, User $invitee, string $acceptLink, string $declineLink)
    {
        $this->themeModel = $theme;
        $this->inviter = $inviter;
        $this->invitee = $invitee;
        $this->acceptLink = $acceptLink;
        $this->declineLink = $declineLink;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Invitation à rejoindre un thème')
            ->markdown('emails.theme.invitation')
            ->with([
                'theme' => $this->themeModel,
            ]);
    }
}
