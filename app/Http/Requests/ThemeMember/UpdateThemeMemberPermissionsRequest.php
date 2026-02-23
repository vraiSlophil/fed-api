<?php

namespace App\Http\Requests\ThemeMember;

use Illuminate\Foundation\Http\FormRequest;

class UpdateThemeMemberPermissionsRequest extends FormRequest
{
    /**
     * Allow validation of permission updates for an existing theme member.
     *
     * @return bool Always true because member-management rights are enforced by policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for the full set of theme permission toggles.
     *
     * @return array Validation constraints keyed by input field name.
     */
    public function rules(): array
    {
        return [
            'can_view' => ['required', 'boolean'],
            'can_update_theme' => ['required', 'boolean'],
            'can_add_task' => ['required', 'boolean'],
            'can_edit_task' => ['required', 'boolean'],
            'can_delete_task' => ['required', 'boolean'],
            'can_validate_task' => ['required', 'boolean'],
        ];
    }
}
