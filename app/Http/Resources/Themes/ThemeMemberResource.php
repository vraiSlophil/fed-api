<?php

namespace App\Http\Resources\Themes;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class ThemeMemberResource extends ApiResource
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
            'user_id' => $this->value('user_id'),
            'username' => $this->value('username'),
            'email' => $this->value('email'),
            'first_name' => $this->value('first_name'),
            'last_name' => $this->value('last_name'),
            'avatar_path' => $this->value('avatar_path'),
            'status' => $this->value('status'),
            'created_at' => $this->value('created_at'),
            'permissions' => [
                'can_view' => data_get($this->resource, 'permissions.can_view'),
                'can_update_theme' => data_get($this->resource, 'permissions.can_update_theme'),
                'can_add_task' => data_get($this->resource, 'permissions.can_add_task'),
                'can_edit_task' => data_get($this->resource, 'permissions.can_edit_task'),
                'can_delete_task' => data_get($this->resource, 'permissions.can_delete_task'),
                'can_validate_task' => data_get($this->resource, 'permissions.can_validate_task'),
            ],
        ];
    }
}
