<?php

namespace App\Domain\Invitations\Actions;

use App\Domain\Invitations\Services\InvitationService;
use App\Exceptions\ApiException;
use App\Invitations\Invitable;
use App\Models\Invitations\Invitation;

class InvitationActionService
{
    public function respond(Invitation $invitation, string $status, ?string $targetPlaygroundId, InvitationService $invitationService): array
    {
        if ($invitation->status === 'accepted' || $invitation->status === 'declined') {
            throw new ApiException('invitation.already_responded', [], 409, 'Invitation already responded');
        }

        if ($invitation->status === 'expired') {
            throw new ApiException('invitation.expired', [], 410, 'Invitation expired');
        }

        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            $invitationService->expireInvitation($invitation);
            throw new ApiException('invitation.expired', [], 410, 'Invitation expired');
        }

        if ($status === 'accepted') {
            $invitable = $invitation->invitable;

            if (! $invitable instanceof Invitable) {
                throw new ApiException('invitation.invalid', [], 400, 'Unsupported invitation type');
            }

            $permission = $invitable->acceptInvitation($invitation, $targetPlaygroundId);
            $invitationService->markAccepted($invitation);

            return [
                'status' => 'accepted',
                'permission' => $permission->fresh(['theme', 'targetPlayground']),
            ];
        }

        $invitationService->markDeclined($invitation);

        return [
            'status' => 'declined',
            'permission' => null,
        ];
    }
}
