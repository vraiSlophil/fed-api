<?php

namespace App\Http\Requests\Playground;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $actorId = (string) ($this->user()?->user_id ?? '00000000-0000-0000-0000-000000000000');

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'string',
                'max:140',
                Rule::unique('playgrounds', 'slug')->where(fn ($query) => $query->where('user_id', $actorId)),
            ],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'size:7', 'regex:/^#[0-9A-F]{6}$/i'],
            'background_color' => ['nullable', 'string', 'size:7', 'regex:/^#[0-9A-F]{6}$/i'],
            'style' => ['nullable', 'array'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
