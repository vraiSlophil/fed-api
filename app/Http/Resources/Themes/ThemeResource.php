<?php

namespace App\Http\Resources\Themes;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * @mixin \App\Models\Themes\Theme
 */
class ThemeResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'theme_id' => $this->value('theme_id'),
            'playground_id' => $this->value('playground_id'),
            'owner_id' => $this->value('owner_id'),
            'title' => $this->value('title'),
            'color' => $this->value('color'),
            'visibility' => $this->value('visibility'),
            'target_playground_id' => $this->value('target_playground_id'),
            'created_at' => $this->value('created_at'),
            'updated_at' => $this->value('updated_at'),
        ];

        $permissions = $this->value('permissions');

        if ($permissions !== null) {
            $payload['permissions'] = $this->transformResource($permissions, ThemePermissionResource::class, $request);
        }

        return $payload;
    }
}
