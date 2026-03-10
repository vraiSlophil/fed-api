<?php

namespace App\Http\Resources\Invitations;

use App\Http\Resources\ApiResource;
use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Request;

/**
 * @mixin \App\Models\Invitations\Invitation
 */
class InvitationResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'invitation_id' => $this->value('invitation_id'),
            'status' => $this->value('status'),
            'created_at' => $this->value('created_at'),
            'expires_at' => $this->value('expires_at'),
            'inviter' => $this->transformResource($this->value('inviter'), UserResource::class, $request),
            'invitable' => $this->transformResource($this->value('invitable'), InvitationInvitableResource::class, $request),
        ];
    }
}
