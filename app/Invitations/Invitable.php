<?php

namespace App\Invitations;

use App\Models\Invitations\Invitation;
use App\Models\Themes\ThemeUserPermission;

interface Invitable
{
    /**
     * Apply an accepted invitation to the implementing resource.
     *
     * @param  Invitation  $invitation  Invitation instance being processed by this method.
     * @param  ?string  $targetPlaygroundId  Identifier of the destination playground for shared access.
     * @return ThemeUserPermission ThemeUserPermission instance returned after successful execution.
     */
    public function acceptInvitation(Invitation $invitation, ?string $targetPlaygroundId): ThemeUserPermission;
}
