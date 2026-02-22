<?php

namespace App\Domain\Invitations\Services;

use App\Exceptions\ApiException;
use App\Mail\InvitationAccepted;
use App\Mail\InvitationCreated;
use App\Mail\InvitationDeclined;
use App\Mail\InvitationExpired;
use App\Models\Invitations\Invitation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class InvitationService
{
    public function __construct(
        private readonly InvitationLinkGenerator $linkGenerator
    ) {}

    public function sendCreatedEmail(Invitation $invitation): void
    {
        $invitation->loadMissing(['inviter', 'invitee', 'invitable']);

        if (! $invitation->invitee || ! $invitation->inviter) {
            throw new ApiException('invitation.invalid', [], 400, 'Invalid invitation users');
        }

        if (! $invitation->invitable) {
            throw new ApiException('invitation.invalid', [], 400, 'Unsupported invitation type');
        }

        $links = $this->linkGenerator->buildSignedLinks($invitation);

        try {
            Mail::to($invitation->invitee->email)
                ->queue((new InvitationCreated(
                    $invitation,
                    $links['accept'],
                    $links['decline']
                ))->onQueue(config('queue.mail_queues.invitation', 'emails-invitation')));
        } catch (Throwable $e) {
            Log::error('Invitation created email failed to dispatch', [
                'invitation_id' => $invitation->invitation_id,
                'invitee_user_id' => $invitation->invitee_user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function markAccepted(Invitation $invitation): void
    {
        $invitation->update(['status' => 'accepted']);

        $this->sendAcceptedEmail($invitation);
    }

    public function markDeclined(Invitation $invitation): void
    {
        $invitation->update(['status' => 'declined']);

        $this->sendDeclinedEmail($invitation);
    }

    public function expireInvitation(Invitation $invitation): bool
    {
        if ($invitation->status !== 'pending') {
            return false;
        }

        if (! $invitation->expires_at || $invitation->expires_at->isFuture()) {
            return false;
        }

        $invitation->update(['status' => 'expired']);
        $this->sendExpiredEmail($invitation);

        return true;
    }

    public function sendAcceptedEmail(Invitation $invitation): void
    {
        $invitation->loadMissing(['inviter', 'invitee', 'invitable']);

        if (! $invitation->inviter) {
            return;
        }

        try {
            Mail::to($invitation->inviter->email)
                ->queue((new InvitationAccepted($invitation))
                    ->onQueue(config('queue.mail_queues.invitation', 'emails-invitation')));
        } catch (Throwable $e) {
            Log::error('Invitation accepted email failed to dispatch', [
                'invitation_id' => $invitation->invitation_id,
                'inviter_user_id' => $invitation->inviter_user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendDeclinedEmail(Invitation $invitation): void
    {
        $invitation->loadMissing(['inviter', 'invitee', 'invitable']);

        if (! $invitation->inviter) {
            return;
        }

        try {
            Mail::to($invitation->inviter->email)
                ->queue((new InvitationDeclined($invitation))
                    ->onQueue(config('queue.mail_queues.invitation', 'emails-invitation')));
        } catch (Throwable $e) {
            Log::error('Invitation declined email failed to dispatch', [
                'invitation_id' => $invitation->invitation_id,
                'inviter_user_id' => $invitation->inviter_user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendExpiredEmail(Invitation $invitation): void
    {
        $invitation->loadMissing(['inviter', 'invitee', 'invitable']);

        if (! $invitation->inviter) {
            return;
        }

        try {
            Mail::to($invitation->inviter->email)
                ->queue((new InvitationExpired($invitation))
                    ->onQueue(config('queue.mail_queues.invitation', 'emails-invitation')));
        } catch (Throwable $e) {
            Log::error('Invitation expired email failed to dispatch', [
                'invitation_id' => $invitation->invitation_id,
                'inviter_user_id' => $invitation->inviter_user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
