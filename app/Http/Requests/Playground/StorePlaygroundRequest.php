<?php

namespace App\Http\Requests\Playground;

use Illuminate\Foundation\Http\FormRequest;

class StorePlaygroundRequest extends FormRequest
{
    /**
     * Allow playground creation payload validation.
     *
     * @return bool Always true because ownership is derived from the authenticated user.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for creating playground metadata and style options.
     *
     * @return array Validation constraints keyed by input field name.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'size:7', 'regex:/^#[0-9A-F]{6}$/i'],
            'background_color' => ['nullable', 'string', 'size:7', 'regex:/^#[0-9A-F]{6}$/i'],
            'style' => ['nullable', 'array'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
