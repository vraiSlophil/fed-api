<?php

namespace App\Http\Requests\Theme;

use Illuminate\Foundation\Http\FormRequest;

class UpdateThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:150'],
            'color' => ['sometimes', 'string', 'size:7'],
            'playground_id' => ['sometimes', 'uuid', 'exists:playgrounds,playground_id'],
        ];
    }
}
