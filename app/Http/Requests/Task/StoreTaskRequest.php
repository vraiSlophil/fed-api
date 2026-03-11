<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    /**
     * Allow task creation payload validation.
     *
     * @return bool Always true because permission checks run against the target theme.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for task creation fields.
     *
     * @return array Validation constraints keyed by input field name.
     */
    public function rules(): array
    {
        return [
            'theme_id' => ['required', 'uuid', 'exists:themes,theme_id'],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:todo,in_progress,done'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'theme_id' => ['description' => 'Theme that will own the task.', 'example' => '278fdd58-2050-4556-9393-8195d1a4ed74'],
            'title' => ['description' => 'Human-readable task title.', 'example' => 'Prepare release notes'],
            'status' => ['description' => 'Initial task status.', 'example' => 'todo'],
        ];
    }
}
