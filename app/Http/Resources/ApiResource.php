<?php

namespace App\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class ApiResource extends JsonResource
{
    protected function value(string $key, mixed $default = null): mixed
    {
        return data_get($this->resource, $key, $default);
    }

    protected function relationLoaded(string $relation): bool
    {
        return $this->resource instanceof Model && $this->resource->relationLoaded($relation);
    }

    protected function transformResource(mixed $resource, string $resourceClass, Request $request): mixed
    {
        if ($resource === null) {
            return null;
        }

        return $resourceClass::make($resource)->resolve($request);
    }
}
