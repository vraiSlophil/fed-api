<?php

namespace App\Http\Requests\Task;

use App\Support\Pagination\OffsetPagination;
use Illuminate\Foundation\Http\FormRequest;

class ListTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...OffsetPagination::queryRules(),
            'theme_id' => ['sometimes', 'uuid', 'exists:themes,theme_id'],
            'status' => ['sometimes', 'string', 'in:todo,in_progress,done'],
            'statuses' => ['sometimes', 'string'],
            'archived' => ['sometimes', 'boolean'],
            'validated' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'string', 'in:asc,desc'],
            'search' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
