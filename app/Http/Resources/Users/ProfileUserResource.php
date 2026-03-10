<?php

namespace App\Http\Resources\Users;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * @mixin \App\Models\Auth\User
 */
class ProfileUserResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->value('user_id'),
            'username' => $this->value('username'),
            'email' => $this->value('email'),
            'first_name' => $this->value('first_name'),
            'last_name' => $this->value('last_name'),
            'avatar_path' => $this->value('avatar_path'),
            'email_verified_at' => $this->value('email_verified_at'),
            'created_at' => $this->value('created_at'),
            'updated_at' => $this->value('updated_at'),
        ];
    }
}
