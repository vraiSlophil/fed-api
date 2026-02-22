<?php

namespace App\Http\Requests\ThemeMember;

use Illuminate\Foundation\Http\FormRequest;

class UpdateThemeMemberPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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
