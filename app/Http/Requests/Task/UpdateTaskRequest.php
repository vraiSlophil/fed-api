<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    /**
     * Allow task update payload validation.
     *
     * @return bool Always true because edit permissions are enforced in policies/services.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for editable task fields.
     *
     * @return array Validation constraints keyed by input field name.
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'string', 'in:todo,in_progress,done'],
            'archived_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
