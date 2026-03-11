<?php

namespace App\Http\Resources\Docs\Tasks;

use App\Http\Resources\Tasks\TaskResource;
use Illuminate\Http\Request;

/**
 * @mixin \App\Models\Tasks\Task
 */
class DocumentedTaskResource extends TaskResource
{
    /**
     * Transform the resource into an array with example-friendly nullable fields.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = parent::toArray($request);

        if ($payload['completed_at'] === null) {
            $payload['completed_at'] = '2026-03-10T11:00:00+00:00';
        }

        return $payload;
    }
}
