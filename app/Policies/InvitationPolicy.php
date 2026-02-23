<?php

namespace App\Policies;

use App\Models\Auth\User;
use App\Models\Invitations\Invitation;

class InvitationPolicy
{
    /**
     * Determine whether the user can view the specified resource.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Invitation  $invitation  Invitation instance being processed by this method.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function view(User $user, Invitation $invitation): bool
    {
        return $invitation->invitee_user_id === $user->user_id;
    }

    /**
     * Determine whether the user can answer (accept/decline) the invitation.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  Invitation  $invitation  Invitation instance being processed by this method.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function respond(User $user, Invitation $invitation): bool
    {
        return $invitation->invitee_user_id === $user->user_id;
    }
}
