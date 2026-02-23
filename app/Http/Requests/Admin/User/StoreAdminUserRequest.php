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
}
