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
            'can_view' => ['sometimes', 'required', 'boolean'],
            'can_update_theme' => ['sometimes', 'required', 'boolean'],
            'can_add_task' => ['sometimes', 'required', 'boolean'],
            'can_edit_task' => ['sometimes', 'required', 'boolean'],
            'can_delete_task' => ['sometimes', 'required', 'boolean'],
            'can_validate_task' => ['sometimes', 'required', 'boolean'],
            'status' => ['sometimes', 'required', 'string', 'in:active,revoked'],
            'target_playground_id' => ['sometimes', 'required', 'uuid', 'exists:playgrounds,playground_id'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'can_view' => ['description' => 'Whether the member can view the theme.', 'example' => true],
            'can_update_theme' => ['description' => 'Whether the member can edit theme metadata.', 'example' => false],
            'can_add_task' => ['description' => 'Whether the member can create tasks.', 'example' => true],
            'can_edit_task' => ['description' => 'Whether the member can update tasks.', 'example' => true],
            'can_delete_task' => ['description' => 'Whether the member can delete tasks.', 'example' => false],
            'can_validate_task' => ['description' => 'Whether the member can validate completed tasks.', 'example' => false],
            'status' => ['description' => 'Membership status.', 'example' => 'active'],
            'target_playground_id' => ['description' => 'Playground used by the member for this shared theme.', 'example' => '5e4f4aa4-a102-4878-8b86-9623a02f2f01'],
        ];
    }
}
