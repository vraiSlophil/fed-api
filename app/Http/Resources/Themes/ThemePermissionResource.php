<?php

namespace App\Http\Resources\Themes;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * @mixin \App\Models\Themes\ThemeUserPermission
 */
class ThemePermissionResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'permission_id' => $this->value('permission_id'),
            'theme_id' => $this->value('theme_id'),
            'user_id' => $this->value('user_id'),
            'target_playground_id' => $this->value('target_playground_id'),
            'can_view' => $this->value('can_view'),
            'can_update_theme' => $this->value('can_update_theme'),
            'can_add_task' => $this->value('can_add_task'),
            'can_edit_task' => $this->value('can_edit_task'),
            'can_delete_task' => $this->value('can_delete_task'),
            'can_validate_task' => $this->value('can_validate_task'),
            'status' => $this->value('status'),
            'created_at' => $this->value('created_at'),
            'updated_at' => $this->value('updated_at'),
        ];
    }
}
