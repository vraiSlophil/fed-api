<?php

namespace App\Http\Requests\ThemeMember;

use Illuminate\Foundation\Http\FormRequest;

class MoveThemeMemberPlaygroundRequest extends FormRequest
{
    /**
     * Allow validation of member-playground reassignment payloads.
     *
     * @return bool Always true because member-management rights are enforced by policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for selecting the destination playground.
     *
     * @return array Validation constraints keyed by input field name.
     */
    public function rules(): array
    {
        return [
            'target_playground_id' => ['required', 'uuid', 'exists:playgrounds,playground_id'],
        ];
    }
}
