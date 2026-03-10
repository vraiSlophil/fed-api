<?php

namespace App\Http\Requests\User;

use App\Models\Auth\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PatchUserRequest extends FormRequest
{
    /**
     * Allow request validation; route authorization is enforced by policies.
     *
     * @return bool Always true because authorization is handled outside this request object.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for unified user updates through PATCH /users/{user}.
     *
     * @return array Validation constraints keyed by input field name.
     */
    public function rules(): array
    {
        /** @var User|null $target */
        $target = $this->route('user');
        $actor = $this->user();
        $isAdmin = $actor?->role_power >= 100;

        return [
            'username' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('users')->ignore($target?->user_id, 'user_id')],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($target?->user_id, 'user_id')],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'confirmed'],
            'current_password' => [Rule::requiredIf(fn () => $this->filled('password') && ! $isAdmin), 'string'],
            'avatar' => ['sometimes', 'required', 'image', 'max:2048'],
            'role_power' => $isAdmin
                ? ['sometimes', 'required', 'exists:roles,power']
                : ['prohibited'],
            'blocked_at' => $isAdmin
                ? ['sometimes', 'nullable', 'date']
                : ['prohibited'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'username' => ['description' => 'Updated username.', 'example' => 'johnny'],
            'email' => ['description' => 'Updated email address.', 'example' => 'johnny@example.com'],
            'first_name' => ['description' => 'Updated first name.', 'example' => 'John'],
            'last_name' => ['description' => 'Updated last name.', 'example' => 'Doe'],
            'password' => ['description' => 'New password when rotating credentials.', 'example' => 'N3w!StrongPass'],
            'password_confirmation' => ['description' => 'Repeat of the new password.', 'example' => 'N3w!StrongPass'],
            'current_password' => ['description' => 'Current password required for self-service password changes.', 'example' => 'Str0ng!Passw0rd'],
            'avatar' => ['description' => 'Optional avatar upload.', 'type' => 'file'],
            'role_power' => ['description' => 'Admin-only role power update.', 'example' => '100'],
            'blocked_at' => ['description' => 'Admin-only block timestamp. Send null to unblock the user.', 'example' => '2026-03-10T10:00:00+00:00'],
        ];
    }
}
