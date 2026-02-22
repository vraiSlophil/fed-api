<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'theme_id' => ['required', 'uuid', 'exists:themes,theme_id'],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:todo,in_progress,done'],
        ];
    }
}
