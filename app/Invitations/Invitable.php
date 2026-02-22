<?php

namespace App\Invitations;

use App\Models\Invitations\Invitation;
use App\Models\Themes\ThemeUserPermission;

interface Invitable
{
    public function acceptInvitation(Invitation $invitation, ?string $targetPlaygroundId): ThemeUserPermission;
}
