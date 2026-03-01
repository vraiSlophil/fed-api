<?php

namespace App\Domain\Invitations\Actions;

use App\Domain\Invitations\Services\InvitationService;
use App\Exceptions\ApiException;
use App\Invitations\Invitable;
use App\Models\Auth\User;
use App\Models\Invitations\Invitation;
use App\Models\Themes\Theme;
use App\Models\Themes\ThemeUserPermission;
use Carbon\Carbon;

class InvitationActionService
{
    /**
     * Resolve the API invitable type alias to the internal model class.
     *
     * @param  string  $invitableType  Invitable type provided by API clients.
     * @return class-string<Theme> Internal model class name.
     *
     * @throws \App\Exceptions\ApiException When the operation cannot be completed.
     */
    private function normalizeInvitableType(string $invitableType): string
    {
        return match ($invitableType) {
            'theme', Theme::class => Theme::class,
            default => throw new ApiException('invitation.invalid', [], 400, 'Unsupported invitation type'),
        };
    }

    /**
     * Create a new invitation resource.
     *
     * @param  User  $actor  Authenticated user who initiates the action.
     * @param  array  $validated  Validated payload extracted from the request.
     * @param  InvitationService  $invitationService  Service responsible for invitation operations.
     * @return Invitation Invitation instance returned after successful execution.
     *
     * @throws \App\Exceptions\ApiException When the operation cannot be completed.
     */
    public function create(User $actor, array $validated, InvitationService $invitationService): Invitation
    {
        $this->normalizeInvitableType((string) $validated['invitable_type']);

        /** @var Theme $theme */
        $theme = Theme::query()
            ->where('theme_id', (string) $validated['invitable_id'])
            ->firstOrFail();

        if (! $theme->isOwnedBy($actor->user_id)) {
            throw new ApiException('permission.denied', [], 403, 'Only the owner can invite members');
        }

        $inviteeUserId = (string) $validated['invitee_user_id'];
        if ($theme->owner_id === $inviteeUserId) {
            throw new ApiException('permission.denied', [], 403, 'Cannot invite theme owner');
        }

        if (ThemeUserPermission::query()
            ->where('theme_id', $theme->theme_id)
            ->where('user_id', $inviteeUserId)
            ->exists()) {
            throw new ApiException('theme.member.already_exists', ['user_id' => $inviteeUserId], 409, 'User is already a member of this theme');
        }

        if (Invitation::query()
            ->where('invitee_user_id', $inviteeUserId)
            ->where('invitable_type', Theme::class)
            ->where('invitable_id', $theme->theme_id)
            ->where('status', 'pending')
            ->exists()) {
            throw new ApiException('theme.invitation.already_exists', ['user_id' => $inviteeUserId], 409, 'User has already been invited to this theme');
        }

        $expiresAt = array_key_exists('expires_at', $validated) && $validated['expires_at']
            ? Carbon::parse((string) $validated['expires_at'])
            : now()->addDays((int) config('invitations.expires_days', 7));

        $invitation = Invitation::query()->create([
            'inviter_user_id' => $actor->user_id,
            'invitee_user_id' => $inviteeUserId,
            'invitable_type' => Theme::class,
            'invitable_id' => $theme->theme_id,
            'payload' => $validated['payload'],
            'status' => 'pending',
            'expires_at' => $expiresAt,
        ]);

        $invitationService->sendCreatedEmail($invitation);

        return $invitation->fresh(['inviter', 'invitee', 'invitable']);
    }

    /**
     * Apply a status transition to an invitation with expiration safeguards.
     *
     * @param  Invitation  $invitation  Invitation instance being processed by this method.
     * @param  string  $status  Requested status value applied by this method.
     * @param  ?string  $targetPlaygroundId  Identifier of the destination playground for shared access.
     * @param  InvitationService  $invitationService  Service responsible for invitation operations.
     * @return array Payload describing invitation response status and permission result.
     *
     * @throws \App\Exceptions\ApiException When the operation cannot be completed.
     */
    public function respond(Invitation $invitation, string $status, ?string $targetPlaygroundId, InvitationService $invitationService): array
    {
        if ($invitation->status === 'expired') {
            throw new ApiException('invitation.expired', [], 410, 'Invitation expired');
        }

        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            $invitationService->expireInvitation($invitation);
            throw new ApiException('invitation.expired', [], 410, 'Invitation expired');
        }

        if ($invitation->status !== 'pending') {
            throw new ApiException('invitation.invalid_transition', ['status' => $invitation->status], 409, 'Only pending invitations can transition');
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

        if ($status === 'declined') {
            $invitationService->markDeclined($invitation);

            return [
                'status' => 'declined',
                'permission' => null,
            ];
        }

        $invitationService->markCanceled($invitation);

        return [
            'status' => 'canceled',
            'permission' => null,
        ];
    }

    /**
     * Delete an invitation when its status is eligible for hard deletion.
     *
     * @param  Invitation  $invitation  Invitation instance being processed by this method.
     * @return void No return value.
     *
     * @throws \App\Exceptions\ApiException When the operation cannot be completed.
     */
    public function delete(Invitation $invitation): void
    {
        if (! in_array($invitation->status, ['declined', 'canceled'], true)) {
            throw new ApiException(
                'invitation.delete_not_allowed_status',
                ['status' => $invitation->status],
                409,
                'Invitation must be canceled or declined before deletion'
            );
        }

        $invitation->delete();
    }
}
