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

    public function queryParameters(): array
    {
        return [
            'page' => ['description' => 'Results page number.', 'example' => 1],
            'per_page' => ['description' => 'Number of tasks per page.', 'example' => 15],
            'theme_id' => ['description' => 'Restrict the list to one theme.', 'example' => '278fdd58-2050-4556-9393-8195d1a4ed74'],
            'status' => ['description' => 'Restrict the list to one canonical task status.', 'example' => 'todo'],
            'statuses' => ['description' => 'Comma-separated list of statuses.', 'example' => 'todo,in_progress'],
            'archived' => ['description' => 'Filter archived versus active tasks.', 'example' => false],
            'validated' => ['description' => 'Filter tasks by validation state.', 'example' => true],
            'sort' => ['description' => 'Sort direction.', 'example' => 'desc'],
            'search' => ['description' => 'Free-text search applied to task titles.', 'example' => 'release'],
        ];
    }
}
