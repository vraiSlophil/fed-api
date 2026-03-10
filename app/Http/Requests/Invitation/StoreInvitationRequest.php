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

    public function bodyParameters(): array
    {
        return [
            'invitee_user_id' => [
                'description' => 'User who should receive the invitation.',
                'example' => '9ab53fb4-a4ae-44ec-a2ef-e0f9df9d5c6a',
            ],
            'invitable_type' => [
                'description' => 'Currently only theme invitations are supported.',
                'example' => 'theme',
            ],
            'invitable_id' => [
                'description' => 'Identifier of the theme being shared.',
                'example' => '278fdd58-2050-4556-9393-8195d1a4ed74',
            ],
            'payload' => [
                'description' => 'Invitation payload carrying theme permission flags.',
                'example' => [
                    'permissions' => [
                        'can_view' => true,
                        'can_update_theme' => false,
                        'can_add_task' => true,
                        'can_edit_task' => true,
                        'can_delete_task' => false,
                        'can_validate_task' => false,
                    ],
                ],
            ],
            'expires_at' => [
                'description' => 'Optional custom expiration date for the invitation.',
                'example' => '2026-03-17T10:00:00+00:00',
            ],
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
