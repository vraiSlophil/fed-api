<?php

namespace App\Invitations;

use App\Models\Invitation;
use App\Models\ThemeUserPermission;

interface Invitable
{
    public function acceptInvitation(Invitation $invitation, ?string $targetPlaygroundId): ThemeUserPermission;
}
