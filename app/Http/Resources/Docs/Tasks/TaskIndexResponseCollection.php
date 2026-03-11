<?php

namespace App\Http\Resources\Docs\Tasks;

use App\Http\Resources\Docs\PaginatedApiEnvelopeCollection;

/**
 * @mixin \App\Models\Tasks\Task
 */
class TaskIndexResponseCollection extends PaginatedApiEnvelopeCollection
{
    public $collects = DocumentedTaskResource::class;

    protected ?string $messageCode = 'task.list';
}
