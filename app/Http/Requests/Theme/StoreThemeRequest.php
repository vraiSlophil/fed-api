<?php

namespace App\Http\Requests\Theme;

use Illuminate\Foundation\Http\FormRequest;

class StoreThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'color' => ['required', 'string', 'size:7'],
            'playground_id' => ['required', 'uuid', 'exists:playgrounds,playground_id'],
        ];
    }
}
