<?php

namespace App\Http\Resources\Users;

use App\Http\Resources\ApiResource;
use App\Http\Resources\Themes\ThemeResource;
use Illuminate\Http\Request;

/**
 * @mixin \App\Models\Auth\User
 */
class UserResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'user_id' => $this->value('user_id'),
            'username' => $this->value('username'),
            'email' => $this->value('email'),
            'first_name' => $this->value('first_name'),
            'last_name' => $this->value('last_name'),
            'avatar_path' => $this->value('avatar_path'),
            'role_power' => $this->value('role_power'),
            'email_verified_at' => $this->value('email_verified_at'),
            'blocked_at' => $this->value('blocked_at'),
            'last_login_at' => $this->value('last_login_at'),
            'created_at' => $this->value('created_at'),
            'updated_at' => $this->value('updated_at'),
        ];

        $role = is_array($this->resource)
            ? data_get($this->resource, 'role')
            : ($this->relationLoaded('role') ? $this->resource->getRelation('role') : null);

        if ($role !== null) {
            $payload['role'] = $this->transformResource($role, RoleResource::class, $request);
        }

        $themes = is_array($this->resource)
            ? data_get($this->resource, 'themes')
            : ($this->relationLoaded('themes') ? $this->resource->getRelation('themes') : null);

        if ($themes !== null) {
            $payload['themes'] = ThemeResource::collection($themes)->resolve($request);
        }

        return $payload;
    }
}
