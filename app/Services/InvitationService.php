<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Mail\InvitationAccepted;
use App\Mail\InvitationDeclined;
use App\Mail\InvitationExpired;
use App\Mail\ThemeInvitation;
use App\Models\Invitation;
use App\Models\Theme;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class InvitationService
{
    public function __construct(private readonly InvitationLinkGenerator $linkGenerator) {}

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

    public function markAccepted(Invitation $invitation): void
    {
        $invitation->update(['status' => 'accepted']);
        $this->queueStatusEmail($invitation, 'accepted');
    }

    public function markDeclined(Invitation $invitation): void
    {
        $invitation->update(['status' => 'declined']);
        $this->queueStatusEmail($invitation, 'declined');
    }

    public function expireInvitation(Invitation $invitation): bool
    {
        if ($invitation->status !== 'pending') {
            return false;
        }

        $maxAttempts = (int) config('invitations.expiration_notification_max_attempts', 3);

        if ($invitation->expiration_notification_attempts >= $maxAttempts) {
            $invitation->update(['status' => 'expired']);
            return false;
        }

        $attempts = $invitation->expiration_notification_attempts + 1;
        $invitation->loadMissing(['inviter', 'invitee', 'invitable']);

        $queue = config('queue.mail_queues.invitation', 'emails-invitation');

        try {
            if ($invitation->inviter) {
                Mail::to($invitation->inviter->email)
                    ->queue((new InvitationExpired($invitation))->onQueue($queue));
            }

            $invitation->update([
                'status' => 'expired',
                'expiration_notification_attempts' => $attempts,
                'expiration_notification_last_attempt_at' => now(),
                'expiration_notified_at' => $invitation->inviter ? now() : null,
            ]);

            return true;
        } catch (Throwable $e) {
            $updates = [
                'expiration_notification_attempts' => $attempts,
                'expiration_notification_last_attempt_at' => now(),
            ];

            if ($attempts >= $maxAttempts) {
                $updates['status'] = 'expired';
            }

            $invitation->update($updates);

            Log::warning('Failed to queue invitation expiration email', [
                'invitation_id' => $invitation->invitation_id,
                'attempts' => $attempts,
                'max_attempts' => $maxAttempts,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function queueStatusEmail(Invitation $invitation, string $status): void
    {
        $invitation->loadMissing(['inviter', 'invitee', 'invitable']);

        if (! $invitation->inviter) {
            return;
        }

        $queue = config('queue.mail_queues.invitation', 'emails-invitation');

        $mailable = match ($status) {
            'accepted' => new InvitationAccepted($invitation),
            'declined' => new InvitationDeclined($invitation),
            default => null,
        };

        if (! $mailable) {
            return;
        }

        try {
            Mail::to($invitation->inviter->email)->queue($mailable->onQueue($queue));
        } catch (Throwable $e) {
            Log::warning('Failed to queue invitation status email', [
                'invitation_id' => $invitation->invitation_id,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
