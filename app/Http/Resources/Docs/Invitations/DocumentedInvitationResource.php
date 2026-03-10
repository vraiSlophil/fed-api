<?php

namespace App\Http\Resources\Docs\Invitations;

use App\Http\Resources\ApiResource;
use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Request;

/**
 * @mixin \App\Models\Invitations\Invitation
 */
class DocumentedInvitationResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $inviter = data_get($this->resource, 'inviter');
        $inviterPayload = $inviter !== null
            ? UserResource::make($inviter)->resolve($request)
            : [
                'user_id' => $this->value('inviter_user_id', '2a7188b7-8fd0-4bb9-9f9c-e61c3f4f7b24'),
                'username' => 'owner',
                'email' => 'owner@example.com',
                'first_name' => 'Owner',
                'last_name' => 'User',
                'avatar_path' => null,
                'role_power' => 10,
                'email_verified_at' => null,
                'blocked_at' => null,
                'last_login_at' => null,
                'created_at' => null,
                'updated_at' => null,
            ];

        return [
            'invitation_id' => $this->value('invitation_id'),
            'status' => $this->value('status', 'pending'),
            'created_at' => $this->value('created_at'),
            'expires_at' => $this->value('expires_at'),
            'inviter' => $inviterPayload,
            'invitable' => [
                'type' => data_get($this->resource, 'invitable.type', 'theme'),
                'id' => data_get($this->resource, 'invitable.id', $this->value('invitable_id', '278fdd58-2050-4556-9393-8195d1a4ed74')),
                'title' => data_get($this->resource, 'invitable.title', 'Roadmap'),
                'color' => data_get($this->resource, 'invitable.color', '#2563EB'),
            ],
        ];
    }
}
