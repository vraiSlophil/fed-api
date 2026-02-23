<?php

namespace App\Http\Requests\ThemeMember;

use Illuminate\Foundation\Http\FormRequest;

class SearchThemeUsersRequest extends FormRequest
{
    /**
     * Allow validation of invitation candidate search parameters.
     *
     * @return bool Always true because theme member-management rights are enforced by policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for searching users eligible for theme invitations.
     *
     * @return array Validation constraints keyed by input field name.
     */
    public function rules(): array
    {
        return [
            'search' => ['required', 'string', 'min:3'],
            'theme_id' => ['required', 'uuid', 'exists:themes,theme_id'],
        ];
    }
}
