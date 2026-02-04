<?php

namespace App\Mail;

use App\Models\Theme;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class InvitationCreated extends Mailable
{
    use Queueable, SerializesModels;

    public $tries = 3;

    public Theme $invitedTheme;

    public User $inviter;

    public User $invitee;

    public string $acceptLink;

    public string $declineLink;

    public function __construct(Theme $theme, User $inviter, User $invitee, string $acceptLink, string $declineLink)
    {
        $this->invitedTheme = $theme;
        $this->inviter = $inviter;
        $this->invitee = $invitee;
        $this->acceptLink = $acceptLink;
        $this->declineLink = $declineLink;
    }

    public function build(): self
    {
        return $this->subject('Invitation a rejoindre un theme')
            ->markdown('emails.theme.invitation')
            ->with([
                'theme' => $this->invitedTheme,
            ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('Invitation created email failed', [
            'inviter_user_id' => $this->inviter->user_id ?? null,
            'invitee_user_id' => $this->invitee->user_id ?? null,
            'error' => $e->getMessage(),
        ]);
    }
}
