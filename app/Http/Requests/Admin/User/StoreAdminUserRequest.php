<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdminUserRequest extends FormRequest
{
    /**
     * Allow request validation; route authorization is enforced by admin policies.
     *
     * @return bool Always true because authorization is handled outside this request object.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for creating a user account from the admin area.
     *
     * @return array Validation constraints keyed by input field name.
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50', 'unique:users'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'role_power' => ['required', 'exists:roles,power'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'username' => ['description' => 'Username assigned to the created account.', 'example' => 'admin.jane'],
            'email' => ['description' => 'Unique email address for the account.', 'example' => 'jane.admin@example.com'],
            'password' => ['description' => 'Initial password for the account.', 'example' => 'Adm1n!StrongPass'],
            'password_confirmation' => ['description' => 'Repeat of the password field.', 'example' => 'Adm1n!StrongPass'],
            'first_name' => ['description' => 'Optional first name.', 'example' => 'Jane'],
            'last_name' => ['description' => 'Optional last name.', 'example' => 'Admin'],
            'role_power' => ['description' => 'Role power assigned to the user.', 'example' => 100],
            'avatar' => ['description' => 'Optional avatar image upload.', 'type' => 'file'],
        ];
    }
}
