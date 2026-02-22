<?php

namespace App\Policies;

use App\Models\Auth\User;
use App\Models\Invitations\Invitation;

class InvitationPolicy
{
    public function view(User $user, Invitation $invitation): bool
    {
        return $invitation->invitee_user_id === $user->user_id;
    }

    public function respond(User $user, Invitation $invitation): bool
    {
        return $invitation->invitee_user_id === $user->user_id;
    }
}
