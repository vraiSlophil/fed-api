<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Mail\ThemeInvitation;
use App\Models\Invitation;
use App\Models\Theme;
use Illuminate\Support\Facades\Mail;
use Throwable;

class InvitationService
{
    public function __construct(private readonly InvitationLinkGenerator $linkGenerator)
    {
    }

    public function sendCreatedEmail(Invitation $invitation): void
    {
        $invitation->loadMissing(['inviter', 'invitee', 'invitable']);

        if (! $invitation->invitee || ! $invitation->inviter) {
            throw new ApiException('invitation.invalid', [], 400, 'Invalid invitation users');
        }

        if (! $invitation->invitable instanceof Theme) {
            throw new ApiException('invitation.invalid', [], 400, 'Unsupported invitation type');
        }

        $links = $this->linkGenerator->buildSignedLinks($invitation);

        try {
            Mail::to($invitation->invitee->email)
                ->queue((new ThemeInvitation(
                    $invitation->invitable,
                    $invitation->inviter,
                    $invitation->invitee,
                    $links['accept'],
                    $links['decline']
                ))->onQueue(config('queue.mail_queues.invitation', 'emails-invitation')));
        } catch (Throwable $e) {
            $invitation->delete();
            throw new ApiException('common.error', [], 500, 'Error sending invitation email');
        }
    }
}
