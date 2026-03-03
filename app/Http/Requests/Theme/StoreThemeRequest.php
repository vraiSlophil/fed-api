<?php

namespace App\Http\Requests\Theme;

use Illuminate\Foundation\Http\FormRequest;

class StoreThemeRequest extends FormRequest
{
    /**
     * Allow theme creation payload validation.
     *
     * @return bool Always true because ownership checks are performed in service/policy layers.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for creating a theme and linking it to a playground.
     *
     * @return array Validation constraints keyed by input field name.
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'color' => ['required', 'string', 'size:7', 'regex:/^#[0-9A-F]{6}$/i'],
            'playground_id' => ['required', 'uuid', 'exists:playgrounds,playground_id'],
        ];
    }
}
