<?php

namespace App\Http\Requests\Invitation;

use App\Domain\Themes\Support\ThemePermissionInvariant;
use App\Models\Themes\Theme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
{
    /**
     * Allow invitation creation payload validation.
     *
     * @return bool Always true because authorization is enforced in controllers/policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for invitation creation.
     *
     * @return array Validation constraints keyed by request field name.
     */
    public function rules(): array
    {
        return [
            'invitee_user_id' => ['required', 'uuid', 'exists:users,user_id'],
            'invitable_type' => ['required', 'string', Rule::in(['theme', Theme::class])],
            'invitable_id' => ['required', 'uuid'],
            'payload' => ['required', 'array'],
            'payload.permissions' => ['required', 'array'],
            'payload.permissions.can_view' => ['required', 'boolean'],
            'payload.permissions.can_update_theme' => ['required', 'boolean'],
            'payload.permissions.can_add_task' => ['required', 'boolean'],
            'payload.permissions.can_edit_task' => ['required', 'boolean'],
            'payload.permissions.can_delete_task' => ['required', 'boolean'],
            'payload.permissions.can_validate_task' => ['required', 'boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /**
     * Validate permission graph coherence after base validation succeeds.
     *
     * @throws \App\Exceptions\ApiException
     */
    protected function passedValidation(): void
    {
        $permissions = $this->validated('payload.permissions');

        ThemePermissionInvariant::ensureCanViewForActionFlags(
            is_array($permissions) ? $permissions : []
        );
    }
}
