<?php

namespace App\Http\Resources\Docs\Invitations;

use App\Http\Resources\Docs\PaginatedApiEnvelopeCollection;

/**
 * @mixin \App\Models\Invitations\Invitation
 */
class InvitationIndexResponseCollection extends PaginatedApiEnvelopeCollection
{
    public $collects = DocumentedInvitationResource::class;

    protected ?string $messageCode = 'invitation.list.success';
}
