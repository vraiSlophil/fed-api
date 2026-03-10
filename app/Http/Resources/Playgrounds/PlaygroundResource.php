<?php

namespace App\Http\Resources\Playgrounds;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * @mixin \App\Models\Playgrounds\Playground
 */
class PlaygroundResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'playground_id' => $this->value('playground_id'),
            'user_id' => $this->value('user_id'),
            'name' => $this->value('name'),
            'slug' => $this->value('slug'),
            'icon' => $this->value('icon'),
            'color' => $this->value('color'),
            'background_color' => $this->value('background_color'),
            'style' => $this->value('style'),
            'preview_image_url' => $this->value('preview_image_url'),
            'preview_updated_at' => $this->value('preview_updated_at'),
            'is_default' => $this->value('is_default'),
            'themes_count' => $this->value('themes_count'),
            'created_at' => $this->value('created_at'),
            'updated_at' => $this->value('updated_at'),
        ];
    }
}
