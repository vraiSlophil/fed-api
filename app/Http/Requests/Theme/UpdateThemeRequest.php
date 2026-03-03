<?php

namespace App\Http\Requests\Theme;

use Illuminate\Foundation\Http\FormRequest;

class UpdateThemeRequest extends FormRequest
{
    /**
     * Allow theme update payload validation.
     *
     * @return bool Always true because write permissions are enforced in policy/service layers.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for partial updates of theme fields.
     *
     * @return array Validation constraints keyed by input field name.
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:150'],
            'color' => ['sometimes', 'string', 'size:7', 'regex:/^#[0-9A-F]{6}$/i'],
            'playground_id' => ['sometimes', 'uuid', 'exists:playgrounds,playground_id'],
        ];
    }
}
