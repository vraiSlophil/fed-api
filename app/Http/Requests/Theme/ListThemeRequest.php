<?php

namespace App\Http\Requests\Theme;

use Illuminate\Foundation\Http\FormRequest;

class ListThemeRequest extends FormRequest
{
    /**
     * Allow validation of optional theme listing filters.
     *
     * @return bool Always true because theme visibility is enforced by query services/policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for optional playground-scoped theme listing.
     *
     * @return array Validation constraints keyed by query parameter name.
     */
    public function rules(): array
    {
        return [
            'playground_id' => ['sometimes', 'uuid', 'exists:playgrounds,playground_id'],
        ];
    }
}
