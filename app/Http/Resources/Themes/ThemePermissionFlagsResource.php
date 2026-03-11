<?php

namespace App\Http\Resources\Themes;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * @mixin \App\Models\Themes\ThemeUserPermission
 */
class ThemePermissionFlagsResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'can_view' => $this->value('can_view'),
            'can_update_theme' => $this->value('can_update_theme'),
            'can_add_task' => $this->value('can_add_task'),
            'can_edit_task' => $this->value('can_edit_task'),
            'can_delete_task' => $this->value('can_delete_task'),
            'can_validate_task' => $this->value('can_validate_task'),
        ];
    }
}
