<?php

namespace App\Http\Resources\Invitations;

use App\Http\Resources\ApiResource;
use App\Models\Themes\Theme;
use Illuminate\Http\Request;

class InvitationInvitableResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->value('type');
        $identifier = $this->value('id');

        if ($this->resource instanceof Theme) {
            $type = 'theme';
            $identifier = $this->resource->theme_id;
        }

        return [
            'type' => $type ?? strtolower(class_basename((string) $this->value('invitable_type'))),
            'id' => $identifier ?? $this->value('invitable_id'),
            'title' => $this->value('title'),
            'color' => $this->value('color'),
        ];
    }
}
