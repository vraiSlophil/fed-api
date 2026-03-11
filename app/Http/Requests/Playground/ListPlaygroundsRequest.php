<?php

namespace App\Http\Requests\Playground;

use Illuminate\Foundation\Http\FormRequest;

class ListPlaygroundsRequest extends FormRequest
{
    /**
     * Allow playground listing payload validation.
     *
     * @return bool Always true because ownership is enforced by scoped queries.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for playground list filters.
     *
     * @return array Validation constraints keyed by query parameter name.
     */
    public function rules(): array
    {
        return [
            'slug' => ['sometimes', 'string', 'max:140'],
        ];
    }

    public function queryParameters(): array
    {
        return [
            'slug' => [
                'description' => 'When provided, returns the playground matching this slug for the current user.',
                'example' => 'home',
            ],
        ];
    }
}
