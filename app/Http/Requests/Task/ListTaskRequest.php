<?php

namespace App\Http\Requests\Task;

use App\Support\Pagination\OffsetPagination;
use Illuminate\Foundation\Http\FormRequest;

class ListTaskRequest extends FormRequest
{
    /**
     * Allow validation of task listing filters and pagination parameters.
     *
     * @return bool Always true because task visibility is enforced by query services/policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for task filters, sorting, and pagination.
     *
     * @return array Validation constraints keyed by query parameter name.
     */
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
