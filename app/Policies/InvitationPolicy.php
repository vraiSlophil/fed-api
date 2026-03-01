<?php

namespace App\Policies;

use App\Models\Auth\User;
use App\Models\Invitations\Invitation;

class InvitationPolicy
{
    /**
     * Determine whether the user has admin privileges.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @return bool True when the condition is met; otherwise, false.
     */
    private function isAdmin(User $user): bool
    {
        return $user->role_power >= 100;
    }

    /**
     * Determine whether the user is the inviter or invitee participant.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Invitation  $invitation  Invitation instance being processed by this method.
     * @return bool True when the condition is met; otherwise, false.
     */
    private function isParticipant(User $user, Invitation $invitation): bool
    {
        return $invitation->inviter_user_id === $user->user_id || $invitation->invitee_user_id === $user->user_id;
    }

    /**
     * Determine whether the user can view the specified resource.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Invitation  $invitation  Invitation instance being processed by this method.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function view(User $user, Invitation $invitation): bool
    {
        return $this->isAdmin($user) || $this->isParticipant($user, $invitation);
    }

    /**
     * Determine whether the user can accept or decline the invitation.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Invitation  $invitation  Invitation instance being processed by this method.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function respondAcceptDecline(User $user, Invitation $invitation): bool
    {
        return $invitation->invitee_user_id === $user->user_id;
    }

    /**
     * Determine whether the user can cancel the invitation.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Invitation  $invitation  Invitation instance being processed by this method.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function cancel(User $user, Invitation $invitation): bool
    {
        return $this->isAdmin($user) || $invitation->inviter_user_id === $user->user_id;
    }

    /**
     * Determine whether the user can delete the invitation.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Invitation  $invitation  Invitation instance being processed by this method.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function delete(User $user, Invitation $invitation): bool
    {
        return $this->isAdmin($user) || $this->isParticipant($user, $invitation);
    }
}
