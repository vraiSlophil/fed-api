<?php

namespace App\Http\Requests\Playground;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlaygroundRequest extends FormRequest
{
    /**
     * Allow playground update payload validation.
     *
     * @return bool Always true because playground ownership checks run in policies/services.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for partial playground metadata updates.
     *
     * @return array Validation constraints keyed by input field name.
     */
    public function rules(): array
    {
        /** @var \App\Models\Playgrounds\Playground|null $playground */
        $playground = $this->route('playground');
        $actorId = (string) $this->user()->user_id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'slug' => [
                'sometimes',
                'string',
                'max:140',
                Rule::unique('playgrounds', 'slug')
                    ->where(fn ($query) => $query->where('user_id', $actorId))
                    ->ignore($playground?->playground_id, 'playground_id'),
            ],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'size:7', 'regex:/^#[0-9A-F]{6}$/i'],
            'background_color' => ['nullable', 'string', 'size:7', 'regex:/^#[0-9A-F]{6}$/i'],
            'style' => ['nullable', 'array'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
