<?php

namespace App\Http\Resources\Users;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * @mixin \App\Models\Auth\Role
 */
class RoleResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'power' => $this->value('power'),
            'name' => $this->value('name'),
        ];
    }
}
